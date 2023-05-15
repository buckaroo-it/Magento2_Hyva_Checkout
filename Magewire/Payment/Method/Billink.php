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
use Buckaroo\Magento2\Helper\Data as HelperData;


class Billink extends Component\Form implements EvaluationInterface
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

    public ?string $vatNumber = null;

    public ?string $phone = null;

    public ?string $gender = null;

    public const RULES_COC = ['required'];

    public const RULES_TOS = ['required', 'boolean', 'accepted'];

    public const RULES_DATE_OF_BIRTH = ['required', 'date', 'before:-18 years'];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ScopeConfigInterface $scopeConfig;

    protected HelperData $helper;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        HelperData $helper
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
        $this->helper = $helper;
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
        $this->coc = $payment->getAdditionalInformation('customer_chamberOfCommerce');
        $this->phone = $payment->getAdditionalInformation('customer_telephone');
        $this->vatNumber = $payment->getAdditionalInformation('customer_VATNumber');
        $this->dateOfBirth = $payment->getAdditionalInformation('customer_DoB');
        $this->gender = $payment->getAdditionalInformation('customer_gender');
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

        $this->updatePaymentField('customer_chamberOfCommerce', $value);
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

    public function updatedVatNumber(string $value): ?string
    {
        $this->updatePaymentField('customer_VATNumber', $value);
        return $value;
    }

    public function updatedGender(string $value): ?string
    {
        $this->validateField('gender', $this->getGenderRules(), $value);
        $this->updatePaymentField('customer_gender', $value);
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
            'tos' => $this->tos,
            'dateOfBirth' => $this->dateOfBirth,
            'gender' => $this->gender
        ];

        if ($this->showPhone()) {
            $values = array_merge($values, ['phone' => $this->phone]);
        }

        if($this->showB2b()) {
            $values = array_merge($values, [
                'coc' => $this->coc
            ]);
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
        $rules = [
            'tos' => self::RULES_TOS,
            'dateOfBirth' => self::RULES_DATE_OF_BIRTH,
            'gender' => $this->getGenderRules()
        ];


        if ($this->showPhone()) {
            $rules = array_merge($rules, ['phone' => $this->getPhoneRules()]);
        }

        if($this->showB2b()) {
            $rules = array_merge($rules, [
                'coc' => self::RULES_COC
            ]);
        }

        return $rules;
    }

    public function showB2b()
    {
        return $this->helper->checkCustomerGroup('buckaroo_magento2_billink');
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

    public function getGenderList(): array
    {
        return [
            ['code' => 'male', 'name' => __('He/him')],
            ['code' => 'female', 'name' => __('She/her')],
            ['code' => 'unknown', 'name' => __('I prefer not to say')]
        ];
    }

    public function getGenderRules(): array
    {
        $genderValues = array_unique(
            array_map(
                function($gender) {
                    return $gender['code'];
                },
                $this->getGenderList()
            )
        );

        return ["required", "in:".implode(",", $genderValues)];
    }
}
