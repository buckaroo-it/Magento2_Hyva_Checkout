<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Quote\Model\Quote;
use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Buckaroo\HyvaCheckout\Model\Validation\Rules\Iban;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\HyvaCheckout\Model\Validation\Rules\NlBeDePhone;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;

abstract class AfterpayBase extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'customer_shipping_country_saved' => 'refresh',
        'billing_address_saved' => 'refresh',
        'customer_billing_country_saved' => 'refresh',
    ];

    public ?bool $tos = true;

    public ?string $dateOfBirth = null;

    public string $fullName = '';

    public ?string $coc = null;

    public ?string $iban = null;

    public ?string $phone = null;

    public ?string $companyName = null;

    public string $selectedBusiness = '1';

    public const RULES_COC = ['required', 'numeric'];

    public const RULES_TOS = ['required', 'boolean', 'accepted'];

    public const RULES_IBAN = ['required', 'alpha_num', 'iban'];

    public const RULES_COMPANY_NAME = ['required'];

    public const RULES_DATE_OF_BIRTH = ['required', 'date', 'before:-18 years'];

    public const RULES_SELECTED_BUSINESS = ['required', 'in:1,2'];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ScopeConfigInterface $scopeConfig;

    protected AbstractConfigProvider $methodConfigProvider;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        AbstractConfigProvider $methodConfigProvider
    ) {
        if($validator->getValidator("nlBeDePhone") === null) {
            $validator->addValidator("nlBeDePhone", new NlBeDePhone());
        }

        if($validator->getValidator("iban") === null) {
            $validator->addValidator("iban", new Iban());
        }
        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->methodConfigProvider = $methodConfigProvider;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $payment = $this->sessionCheckout
            ->getQuote()
            ->getPayment();

        $tos =  $payment->getAdditionalInformation('termsCondition');
        if($tos === null) {
            $tos = true;
            $payment->setAdditionalInformation('termsCondition', $tos);
        }


        $this->tos = $tos === true;
        $this->coc = $payment->getAdditionalInformation('cOCNumber');
        $this->phone = $payment->getAdditionalInformation('customer_telephone');
        $this->iban = $payment->getAdditionalInformation('customer_iban');
        $this->dateOfBirth = $payment->getAdditionalInformation('customer_DoB');
        $this->companyName = $payment->getAdditionalInformation('companyName');

        if($this->showIban()) {
            $this->updatePaymentField('selectedBusiness', '1');
        }

        $this->selectedBusiness = $payment->getAdditionalInformation('selectedBusiness') ?? '1';
        $this->fullName = $this->getFullName();
    }

    /**
     * Validate single field with rules
     *
     * @param string $name
     * @param array $rules
     * @param mixed $value
     * @param string|null $message
     *
     * @return void
     */
    private function validateField(
        string $name,
        array $rules,
        $value,
        $messages = null
    ): void {
        $messageArray = [];

        if (is_string($messages)) {
            $messageArray = [$name => $messages];
        }

        if (is_array($messages)) {
            $messageArray = $messages;
        }

        $this->validateOnly([$name => $rules], $messageArray, [$name => $value]);
    }


    public function updatedCoc(string $value): ?string
    {
        $this->validateField(
            'coc',
            self::RULES_COC,
            $value
        );

        $this->updatePaymentField('cOCNumber', $value);
        return $value;
    }

    public function updatedTos(bool $value): ?bool
    {
        $this->validateField('tos', self::RULES_TOS, $value, "This is a required field");
        $this->updatePaymentField('termsCondition', $value);
        return $value;
    }

    public function updatedPhone(string $value): ?string
    {
        $this->validateField('phone', $this->getPhoneRules(), $value);
        $this->updatePaymentField('customer_telephone', $value);
        return $value;
    }

    public function updatedIban(string $value): ?string
    {
        $this->validateField('iban', self::RULES_IBAN, $value);
        $this->updatePaymentField('customer_iban', $value);
        return $value;
    }

    public function updatedSelectedBusiness(string $value): ?string
    {
        $this->validateField('selectedBusiness', self::RULES_SELECTED_BUSINESS, $value);
        $this->updatePaymentField('selectedBusiness', $value);
        return $value;
    }

    public function updatedCompanyName(string $value): ?string
    {
        $this->validateField('companyName', self::RULES_COMPANY_NAME, $value);
        $this->updatePaymentField('companyName', $value);
        return $value;
    }


    public function updatedDateOfBirth(string $value): ?string
    {
        $this->validateField(
            'dateOfBirth',
            self::RULES_DATE_OF_BIRTH,
            $value,
            ["before" => "You should be at least 18 years old."]
        );
        $this->dateOfBirth = $value;
        $this->updatePaymentField('customer_DoB', $value);
        return $value;
    }


    /**
     * Get rules for phone validations
     *
     * @return array
     */
    private function getPhoneRules(): array
    {
        return ['nlBeDePhone:' . $this->getCountryId(), 'required'];
    }

    /**
     * Update quote with input values
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    private function updatePaymentField(string $name, $value): void
    {
        $value = empty($value) ? null : $value;

        try {
            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation($name, $value);

            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
    }

    /**
     * Set full name on component refresh
     *
     * @return void
     */
    public function hydrateFullName()
    {
        $this->fullName = $this->getFullName();
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        $validation = $this->validator->validate(
            $this->getFormValues(),
            $this->getFormRules()
        );

        if ($validation->fails()) {
            foreach ($validation->errors()->toArray() as $key => $error) {
                $this->error($key, current($error));
            }
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Please fill all required payment fields');
        }

        return $resultFactory->createSuccess();
    }

    /**
     * Get magento quote
     *
     * @return Quote|null
     */
    private function getQuote(): ?Quote
    {
        try {
            return $this->sessionCheckout->getQuote();
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
    }

    /**
     * Get billing address country
     *
     * @return string|null
     */
    public function getCountryId(): ?string
    {
        $quote = $this->getQuote();
        if ($quote === null) {
            return null;
        }
        return $quote->getBillingAddress()->getCountryId();
    }

    /**
     * Get tos link based on country and b2b
     *
     * @return void
     */
    public function getTosLink()
    {
        $countryId = $this->getCountryId();
        $isB2B = $this->showB2b();

        if ($isB2B){
            $tosUrl = 'https://documents.riverty.com/terms_conditions/payment_methods/b2b_invoice/';
        } else {
            $tosUrl = 'https://documents.riverty.com/terms_conditions/payment_methods/invoice/';
        }

        switch ($countryId) {
            case 'DE':
                $tosCountry = 'de_de';
                break;
            case 'AT':
                $tosCountry = 'at_de';
                break;
            case 'NL':
                $tosCountry = 'nl_nl';
                break;
            case 'BE':
                $tosCountry = 'be_nl';
                break;
            case 'FI':
                $tosCountry = 'fi_en';
                break;
            case 'SE':
                $tosCountry = 'se_en';
                break;
            case 'NO':
                $tosCountry = 'no_en';
                break;
            case 'DK':
                $tosCountry = 'dk_en';
                break;
            default:
                $tosCountry = 'nl_en';
                break;
        }
        return $tosUrl . $tosCountry . '/';
    }

    /**
     * Get billing full name
     *
     * @return string
     */
    private function getFullName(): string
    {
        $quote = $this->getQuote();
        if ($quote === null) {
            return '';
        }

        $billingAddress = $quote->getBillingAddress();
        return $billingAddress->getFirstname() . " " . $billingAddress->getLastname();
    }

    /**
     * Get form values
     *
     * @return array
     */
    private function getFormValues(): array
    {
        $values = [
            'tos' => $this->tos
        ];

        if ($this->showBirth()) {
            $values = array_merge($values, ['dateOfBirth' => $this->dateOfBirth]);
        }

        if ($this->showPhone()) {
            $values = array_merge($values, ['phone' => $this->phone]);
        }

        if($this->showB2b()) {
            $values = array_merge($values, [
                'companyName' => $this->companyName,
                'coc' => $this->coc
            ]);
        }

        if($this->showIban()) {
            $values = array_merge($values, ['iban' => $this->iban]);
        }

        return $values;
    }

    /**
     * Get form validation rules
     *
     * @return array
     */
    private function getFormRules(): array
    {
        $rules = ['tos' => self::RULES_TOS];

        if ($this->showBirth()) {
            $rules = array_merge($rules, ['dateOfBirth' => self::RULES_DATE_OF_BIRTH]);
        }

        if ($this->showPhone()) {
            $rules = array_merge($rules, ['phone' => $this->getPhoneRules()]);
        }

        if($this->showB2b()) {
            $rules = array_merge($rules, [
                'companyName' => self::RULES_COMPANY_NAME,
                'coc' => self::RULES_COC
            ]);
        }

        if($this->showIban()) {
            $rules = array_merge($rules, ['iban' => self::RULES_IBAN]);
        }

        return $rules;
    }

    public function showIban(): bool
    {
        return $this->methodConfigProvider->getPaymentMethod() === 1;
    }


    public function showBusinessSelector(): bool
    {
        return $this->methodConfigProvider->getPaymentMethod() !== 1 &&
        $this->methodConfigProvider->getBusiness() === 3;
    }

    public function showB2b()
    {
        return $this->methodConfigProvider->getPaymentMethod() !== 1 &&
        ($this->methodConfigProvider->getBusiness() === 2 || $this->selectedBusiness == 2);
    }

    /**
     * Show birth input in countries BE and NL and not b2b
     *
     * @return boolean
     */
    public function showBirth(): bool
    {
        return in_array($this->getCountryId(), ["BE", "NL"]) && !$this->showB2b();
    }


    /**
     * Show phone number field if phone is invalid
     *
     * @return bool
     */
    public function showPhone(): bool
    {
        $quote = $this->getQuote();

        if ($quote === null) {
            return true;
        }
        $validation = $this->validator->validate(
            ["phone" => $quote->getBillingAddress()->getTelephone()],
            ["phone" => $this->getPhoneRules()]
        );

        return $validation->fails();
    }
}
