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
use Buckaroo\HyvaCheckout\Model\Validation\Rules\NlBeDePhone;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;

class PayPerEmail extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'customer_shipping_country_saved' => 'refresh',
        'billing_address_saved' => 'refresh',
        'customer_billing_country_saved' => 'refresh',
    ];

    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $middleName = null;

    public ?string $email = null;

    public ?string $gender = null;

    public const RULES_NAME = ['required'];

    public const RULES_EMAIL = ['required', 'email'];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository
    ) {
        if ($validator->getValidator("nlBeDePhone") === null) {
            $validator->addValidator("nlBeDePhone", new NlBeDePhone());
        }

        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $quote = $this->getQuote();
        $payment = $quote->getPayment();

        $firstName = $payment->getAdditionalInformation('customer_billingFirstName');
        $lastName = $payment->getAdditionalInformation('customer_billingLastName');
        $middleName = $payment->getAdditionalInformation('customer_billingMiddleName');
        $email = $payment->getAdditionalInformation('customer_email');
        $this->gender = $payment->getAdditionalInformation('customer_gender');

        $billingAddress = $quote->getBillingAddress();

        if ($firstName === null) {
            $firstName = $billingAddress->getFirstname();
            $payment->setAdditionalInformation('customer_billingFirstName', $firstName);
        }

        if ($lastName === null) {
            $lastName = $billingAddress->getLastname();
            $payment->setAdditionalInformation('customer_billingLastName', $lastName);
        }

        if ($middleName === null) {
            $middleName = $billingAddress->getMiddlename();
            $payment->setAdditionalInformation('customer_billingMiddleName', $middleName);
        }

        if ($email === null) {
            $email = $billingAddress->getEmail();
            $payment->setAdditionalInformation('customer_email', $email);
        }

        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->middleName = $middleName;
        $this->email = $email;
        $this->quoteRepository->save($quote);
    }


    public function hydrateFirstName()
    {
        if ($this->firstName === null) {
            $this->firstName = $this->getBillingAddress()->getFirstname();
            $this->getPayment()->setAdditionalInformation('customer_billingFirstName', $this->firstName);
        }
    }

    public function hydrateLastName()
    {
        if ($this->lastName === null) {
            $this->lastName = $this->getBillingAddress()->getLastname();
            $this->getPayment()->setAdditionalInformation('customer_billingLastName', $this->lastName);
        }
    }

    public function hydrateMiddleName()
    {
        if ($this->middleName === null) {
            $this->middleName = $this->getBillingAddress()->getMiddlename();
            $this->getPayment()->setAdditionalInformation('customer_billingMiddleName', $this->middleName);
        }
    }

    public function hydrateEmail()
    {
        if ($this->email === null) {
            $this->email = $this->getBillingAddress()->getEmail();
            $this->getPayment()->setAdditionalInformation('customer_email', $this->email);
        }
    }

    /**
     * Get billing address from quote
     *
     * @return Address
     */
    private function getBillingAddress(): Address
    {
        return $this->sessionCheckout->getQuote()->getBillingAddress();
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


    public function updatedFirstName(string $value): ?string
    {
        $this->validateField('firstName', self::RULES_NAME, $value);
        $this->updatePaymentField('customer_billingFirstName', $value);
        return $value;
    }

    public function updatedLastName(string $value): ?string
    {
        $this->validateField('lastName', self::RULES_NAME, $value);
        $this->updatePaymentField('customer_billingLastName', $value);
        return $value;
    }

    public function updatedMiddleName(string $value): ?string
    {
        $this->updatePaymentField('customer_billingMiddleName', $value);
        return $value;
    }

    public function updatedEmail(string $value): ?string
    {
        $this->validateField('email', self::RULES_EMAIL, $value);
        $this->updatePaymentField('customer_email', $value);
        return $value;
    }

    public function updatedGender(string $value): ?string
    {
        $this->validateField('gender', $this->getGenderRules(), $value);
        $this->updatePaymentField('customer_gender', $value);
        return $value;
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
     * Get form values
     *
     * @return array
     */
    private function getFormValues(): array
    {
        return [
            "firstName" => $this->firstName,
            "lastName" => $this->lastName,
            "email" => $this->email,
            "gender" => $this->gender
        ];
    }

    /**
     * Get form validation rules
     *
     * @return array
     */
    private function getFormRules(): array
    {
        return [
            "firstName" => self::RULES_NAME,
            "lastName" => self::RULES_NAME,
            "email" => self::RULES_EMAIL,
            "gender" => $this->getGenderRules()
        ];
    }

    public function getGenderList(): array
    {
        return [
            ['code' => 1, 'name' => __('He/him')],
            ['code' => 2, 'name' => __('She/her')],
            ['code' => 0, 'name' => __('They/them')],
            ['code' => 9, 'name' => __('I prefer not to say')]
        ];
    }

    public function getGenderRules(): array
    {
        $genderValues = array_unique(
            array_map(
                function ($gender) {
                    return $gender['code'];
                },
                $this->getGenderList()
            )
        );

        return ["required", "in:" . implode(",", $genderValues)];
    }
}
