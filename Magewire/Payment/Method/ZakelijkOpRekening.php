<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Buckaroo\HyvaCheckout\Model\Validation\Rules\NlBeDePhone;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ZakelijkOpRekening as ZakelijkOpRekeningConfigProvider;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Rakit\Validation\Validator;

/**
 * Zakelijk op rekening (ABN AMRO B2B through In3).
 *
 * Collects the same fields as the Magento 2 checkout: the Chamber of Commerce
 * number, and the telephone number when the billing address has no valid one.
 */
class ZakelijkOpRekening extends Component\Form implements EvaluationInterface
{
    public const RULES_COC = ['required'];

    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'customer_shipping_country_saved' => 'refresh',
        'billing_address_saved' => 'refresh',
        'customer_billing_country_saved' => 'refresh',
    ];

    public string $fullName = '';

    public ?string $coc = null;

    public ?string $phone = null;

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ZakelijkOpRekeningConfigProvider $configProvider;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        ZakelijkOpRekeningConfigProvider $configProvider
    ) {
        if ($validator->getValidator('nlBeDePhone') === null) {
            $validator->addValidator('nlBeDePhone', new NlBeDePhone());
        }

        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->configProvider = $configProvider;
    }

    public function mount(): void
    {
        $quote = $this->getQuote();
        if ($quote === null) {
            return;
        }

        $payment = $quote->getPayment();
        $this->coc = $payment->getAdditionalInformation('customer_chamberOfCommerce');
        $this->phone = $payment->getAdditionalInformation('customer_telephone');
        $this->fullName = $this->getFullName();
    }

    public function hydrateFullName(): void
    {
        $this->fullName = $this->getFullName();
    }

    public function updatedCoc(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        $this->validateField('coc', self::RULES_COC, $value);
        $this->updatePaymentField('customer_chamberOfCommerce', $value);

        return $value;
    }

    public function updatedPhone(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        $this->validateField('phone', $this->getPhoneRules(), $value);
        $this->updatePaymentField('customer_telephone', $value);

        return $value;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        $validation = $this->validator->validate($this->getFormValues(), $this->getFormRules());

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
     * Show the telephone field when the billing address has no valid number.
     */
    public function showPhone(): bool
    {
        $quote = $this->getQuote();
        if ($quote === null) {
            return true;
        }

        $validation = $this->validator->validate(
            ['phone' => $quote->getBillingAddress()->getTelephone()],
            ['phone' => $this->getPhoneRules()]
        );

        return $validation->fails();
    }

    public function getTooltipText(): string
    {
        return (string) ($this->getMethodConfig()['tooltipText'] ?? __('Voor iedereen, powered by ABN AMRO,'));
    }

    public function getTooltipUrl(): string
    {
        return (string) ($this->getMethodConfig()['tooltipUrl'] ?? ZakelijkOpRekeningConfigProvider::TOOLTIP_URL);
    }

    public function getCountryId(): ?string
    {
        $quote = $this->getQuote();

        return $quote === null ? null : $quote->getBillingAddress()->getCountryId();
    }

    private function getMethodConfig(): array
    {
        $config = $this->configProvider->getConfig();

        return $config['payment']['buckaroo'][ZakelijkOpRekeningConfigProvider::CODE] ?? [];
    }

    private function getPhoneRules(): array
    {
        return ['required', 'nlBeDePhone:' . $this->getCountryId()];
    }

    private function getFormValues(): array
    {
        $values = ['coc' => $this->coc];

        if ($this->showPhone()) {
            $values['phone'] = $this->phone;
        }

        return $values;
    }

    private function getFormRules(): array
    {
        $rules = ['coc' => self::RULES_COC];

        if ($this->showPhone()) {
            $rules['phone'] = $this->getPhoneRules();
        }

        return $rules;
    }

    /**
     * @param string|array|null $messages
     */
    private function validateField(string $name, array $rules, $value, $messages = null): void
    {
        $messageArray = [];

        if (is_string($messages)) {
            $messageArray = [$name => $messages];
        }

        if (is_array($messages)) {
            $messageArray = $messages;
        }

        $this->validateOnly([$name => $rules], $messageArray, [$name => $value]);
    }

    /**
     * @param mixed $value
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

    private function getQuote(): ?Quote
    {
        try {
            return $this->sessionCheckout->getQuote();
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return null;
    }

    private function getFullName(): string
    {
        $quote = $this->getQuote();
        if ($quote === null) {
            return '';
        }

        $billingAddress = $quote->getBillingAddress();

        return trim($billingAddress->getFirstname() . ' ' . $billingAddress->getLastname());
    }
}
