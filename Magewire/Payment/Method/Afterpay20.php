<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Quote\Model\Quote;
use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20 as MethodConfigProvider;
use Buckaroo\HyvaCheckout\Model\Validation\Rules\NlBeDePhone;

class Afterpay20 extends Component\Form implements EvaluationInterface
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

    public ?string $identificationNumber = null;

    public ?string $phone = null;

    public const RULES_COC = ['required', 'numeric', 'digits_between:0,8'];

    public const RULES_TOS = ['required', 'boolean', 'accepted'];

    public const RULES_ID_NUMBER = ['required', 'alpha_num'];

    public const RULES_DATE_OF_BIRTH = ['required', 'date', 'before:-18 years'];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ScopeConfigInterface $scopeConfig;

    protected MethodConfigProvider $methodConfigProvider;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider
    ) {
        if($validator->getValidator("nlBeDePhone") === null) {
            $validator->addValidator("nlBeDePhone", new NlBeDePhone());
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
        $this->coc = $payment->getAdditionalInformation('customer_coc');
        $this->phone = $payment->getAdditionalInformation('customer_telephone');
        $this->identificationNumber = $payment->getAdditionalInformation('customer_identificationNumber');
        $this->dateOfBirth = $payment->getAdditionalInformation('customer_DoB');
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
            $value,
            ['coc.digits_between' => 'Invalid COC number']
        );

        $this->updatePaymentField('customer_coc', $value);
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

    public function updatedIdentificationNumber(string $value): ?string
    {
        $this->validateField('identificationNumber', self::RULES_ID_NUMBER, $value);
        $this->updatePaymentField('customer_identificationNumber', $value);
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
     * Get tos link based on country
     *
     * @return void
     */
    public function getTosLink()
    {
        $countryId = $this->getCountryId();
        $tosUrl = 'https://documents.myafterpay.com/consumer-terms-conditions/';

        switch ($countryId) {
            case 'DE':
                $tosCountry = 'de_de';
                break;
            case 'AT':
                $tosCountry = 'de_at';
                break;
            case 'NL':
                $tosCountry = 'nl_nl';
                break;
            case 'BE':
                $tosCountry = 'nl_be';
                break;
            case 'FI':
                $tosCountry = 'fi_fi';
                break;
            default:
                $tosCountry = 'en_nl';
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

        if ($this->showCOC()) {
            $values = array_merge($values, ['coc' => $this->coc]);
        }
        if ($this->showPhone()) {
            $values = array_merge($values, ['phone' => $this->phone]);
        }

        if ($this->showIdentificationNumber()) {
            $values = array_merge($values, ['identificationNumber' => $this->identificationNumber]);
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

        if ($this->showCOC()) {
            $rules = array_merge($rules, ['coc' => self::RULES_COC]);
        }
        if ($this->showPhone()) {
            $rules = array_merge($rules, ['phone' => $this->getPhoneRules()]);
        }

        if ($this->showIdentificationNumber()) {
            $rules = array_merge($rules, ['identificationNumber' => self::RULES_ID_NUMBER]);
        }
        return $rules;
    }

    /**
     * Show birth input in countries BE and NL and not b2b
     *
     * @return boolean
     */
    public function showBirth(): bool
    {
        return in_array($this->getCountryId(), ["BE", "NL"]) && !$this->showCOC();
    }

    /**
     * Show coc field when b2b
     *
     * @return boolean
     */
    public function showCOC(): bool
    {
        $quote =  $this->getQuote();
        if ($quote === null) {
            return false;
        }

        $shippingCountry = $quote->getShippingAddress()->getCountryId();
        $billingCompany = $quote->getBillingAddress()->getCompany();
        $shippingCompany = $quote->getShippingAddress()->getCompany();


        return
            $this->methodConfigProvider->getCustomerType() !== AfterpayCustomerType::CUSTOMER_TYPE_B2C &&
            (
                ($this->getCountryId() === 'NL' && !empty(trim((string)$billingCompany))) ||
                ($shippingCountry === 'NL' && !empty(trim((string)$shippingCompany)))
            );
    }

    /**
     * Show identification number for FI
     *
     * @return bool
     */
    public function showIdentificationNumber(): bool
    {
        return $this->getCountryId() === 'FI';
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
