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
use Buckaroo\HyvaCheckout\Model\Validation\Rules\Bic;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;

class SepaDirect extends Component\Form implements EvaluationInterface
{

    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'customer_shipping_country_saved' => 'refresh',
        'billing_address_saved' => 'refresh',
        'customer_billing_country_saved' => 'refresh',
    ];

    public ?string $iban = null;

    public ?string $bic = null;

    public ?string $bankHolder = null;

    public const RULES_IBAN = ['required', 'iban'];

    public const RULES_BIC = ['required', 'bic'];

    public const RULES_BANK_HOLDER = ['required'];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
    ) {
        if($validator->getValidator("bic") === null) {
            $validator->addValidator("bic", new Bic());
        }

        if($validator->getValidator("iban") === null) {
            $validator->addValidator("iban", new Iban());
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
        $payment = $this->sessionCheckout
            ->getQuote()
            ->getPayment();

     
        $this->bic = $payment->getAdditionalInformation('customer_bic');
        $this->iban = $payment->getAdditionalInformation('customer_iban');
        $this->bankHolder = $payment->getAdditionalInformation('customer_account_name');
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

    public function updatedIban(string $value): ?string
    {
        $this->validateField('iban', self::RULES_IBAN, $value);
        $this->updatePaymentField('customer_iban', $value);
        return $value;
    }

    public function updatedBic(string $value): ?string
    {
        $this->validateField('bic', self::RULES_BIC, $value);
        $this->updatePaymentField('customer_bic', $value);
        return $value;
    }

    public function updatedBankHolder(string $value): ?string
    {
        $this->validateField('bankHolder', self::RULES_BANK_HOLDER, $value);
        $this->updatePaymentField('customer_account_name', $value);
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
        $values = [
            "iban" => $this->iban,
            "bankHolder" => $this->bankHolder
        ];

        if(!$this->isNl()) {
            $values = array_merge($values, ["bic" => $this->bic]);
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
            "iban" => self::RULES_IBAN,
            "bankHolder" => self::RULES_BANK_HOLDER
        ];

        if(!$this->isNl()) {
            $rules = array_merge($rules, ["bic" => self::RULES_BIC]);
        }

        return $rules;
    }

    public function isNl(): bool
    {
        return $this->getCountryId() === 'NL';
    }
}
