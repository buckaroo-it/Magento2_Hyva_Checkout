<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard as MethodConfigProvider;

class Creditcard extends Component\Form implements EvaluationInterface
{
    public ?string $cardType = null;

    protected $loader = [
        'cardType' => 'Saving card type'
    ];

    protected $rules = [
        'cardType' => 'required'
    ];

    protected $messages = [
        'cardType:required' => 'A card type is required'
    ];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected ScopeConfigInterface $scopeConfig;

    protected MethodConfigProvider $methodConfigProvider;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        ScopeConfigInterface $scopeConfig,
        MethodConfigProvider $methodConfigProvider
    ) {
        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->scopeConfig = $scopeConfig;
        $this->methodConfigProvider = $methodConfigProvider;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $this->cardType  = $this->sessionCheckout
            ->getQuote()
            ->getPayment()
            ->getAdditionalInformation('card_type');
    }

    /**
     * Listen for bank cardType been updated.
     */
    public function updatedCardType(string $value): ?string
    {
        $this->validateOnly();
        $value = empty($value) ? null : $value;

        try {
            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('card_type', $value);

            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return $value;
    }
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->cardType === null) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('A card type is required');
        }

        return $resultFactory->createSuccess();
    }

    public function getIssuers(): array
    {
        return $this->methodConfigProvider->formatIssuers();
    }

    public function displayAsSelect($storeId = null): bool
    {
        return $this->scopeConfig->getValue(
                MethodConfigProvider::XPATH_SELECTION_TYPE,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            ) === '2';
    }
}
