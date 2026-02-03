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
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3 as In3ConfigProvider;

class In3 extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'customer_shipping_country_saved' => 'refresh',
        'billing_address_saved' => 'refresh',
        'customer_billing_country_saved' => 'refresh',
    ];

    public ?string $dateOfBirth = null;

    public string $fullName = '';

    public ?string $phone = null;

    public const RULES_DATE_OF_BIRTH = ['required', 'date', 'before:-18 years'];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ScopeConfigInterface $scopeConfig;

    protected In3ConfigProvider $in3ConfigProvider;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        In3ConfigProvider $in3ConfigProvider
    ) {
        if($validator->getValidator("nlBeDePhone") === null) {
            $validator->addValidator("nlBeDePhone", new NlBeDePhone());
        }

        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->in3ConfigProvider = $in3ConfigProvider;
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

        $this->phone = $payment->getAdditionalInformation('customer_telephone');
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
    
    public function updatedPhone(string $value): ?string
    {
        $this->validateField('phone', $this->getPhoneRules(), $value);
        $this->updatePaymentField('customer_telephone', $value);
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
            'dateOfBirth' => $this->dateOfBirth,
        ];

        if ($this->showPhone()) {
            $values = array_merge($values, ['phone' => $this->phone]);
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
            'dateOfBirth' => self::RULES_DATE_OF_BIRTH,
        ];

        if ($this->showPhone()) {
            $rules = array_merge($rules, ['phone' => $this->getPhoneRules()]);
        }

        return $rules;
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

    /**
     * Check if financial warning should be shown
     * Only shown for Dutch customers when enabled in config
     *
     * @return bool
     */
    public function showFinancialWarning(): bool
    {
        return $this->getCountryId() === 'NL' && $this->in3ConfigProvider->canShowFinancialWarning();
    }

    /**
     * Get payment method title
     *
     * @return string
     */
    public function getPaymentMethodTitle(): string
    {
        return (string) ($this->in3ConfigProvider->getTitle() ?? 'In3');
    }

    /**
     * Get financial warning message for In3
     *
     * @return string
     */
    public function getFinancialWarningMessage(): string
    {
        $title = $this->getPaymentMethodTitle();

        return (string)__(
            'Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van %1. Door verder te gaan, accepteer je de <a target="_blank" href="%2">Algemene&nbsp;Voorwaarden</a> en bevestig je dat je de <a target="_blank" href="%3">Privacyverklaring</a> en <a target="_blank" href="%4">Cookieverklaring</a> hebt gelezen.',
            $title,
            'https://payin3.eu/nl/legal/',
            'https://payin3.eu/nl/privacyverklaringen/',
            'https://payin3.eu/nl/cookiebeleid/'
        );
    }
}
