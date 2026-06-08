<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay as MethodConfigProvider;
use Psr\Log\LoggerInterface;

class Googlepay extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_method_selected' => 'refresh',
        'payment_method_selected'  => 'refresh',
        'coupon_code_applied'      => 'refresh',
        'coupon_code_revoked'      => 'refresh',
    ];

    /**
     * Google Pay payment token data (mapped to additional_data by Hyvä on order placement)
     */
    public ?string $googlepayPaymentData = null;

    public array $config = [];

    public array $grandTotal = [];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected MethodConfigProvider $methodConfigProvider;

    protected LoggerInterface $logger;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider,
        LoggerInterface $logger,
    ) {
        parent::__construct($validator);

        $this->sessionCheckout      = $sessionCheckout;
        $this->quoteRepository      = $quoteRepository;
        $this->methodConfigProvider = $methodConfigProvider;
        $this->logger               = $logger;
    }

    public function mount(): void
    {
        $this->config = $this->getJsonConfig();
        $this->grandTotal = $this->getGrandTotal();
    }

    public function hydrate(): void
    {
        $this->config = $this->getJsonConfig();
        $this->grandTotal = $this->getGrandTotal();
    }

    /**
     * Store Google Pay payment token data in the quote payment additional information
     */
    public function updateData(string $paymentData): string
    {
        try {
            $this->googlepayPaymentData = $paymentData;

            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('googlepayPaymentData', $paymentData);
            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->logger->error('Hyva GooglePay updateData failed', [
                'message' => $exception->getMessage(),
            ]);
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return $paymentData;
    }

    /**
     * Allow order placement — hyvaCheckout.payment.validate handles Google Pay authorization before this is called
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        return $resultFactory->createSuccess();
    }

    private function getJsonConfig(): array
    {
        try {
            $config = $this->methodConfigProvider->getConfig();

            if (empty($config)) {
                return [];
            }

            if (!isset($config['payment']['buckaroo']['buckaroo_magento2_googlepay'])) {
                return [];
            }

            return $config['payment']['buckaroo']['buckaroo_magento2_googlepay'];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getGrandTotal(): array
    {
        try {
            $quote = $this->sessionCheckout->getQuote();

            if (!$quote || !$quote->getId()) {
                return [];
            }

            $quote->collectTotals();
            $totals = $quote->getTotals();

            if (!isset($totals['grand_total'])) {
                return [];
            }

            $total = $totals['grand_total'];

            return [
                'label' => $total->getData('title'),
                'amount' => $total->getData('value'),
                'type' => 'final',
            ];
        } catch (\Throwable $exception) {
            return [];
        }
    }
}
