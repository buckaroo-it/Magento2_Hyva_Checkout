<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Clicktopay as MethodConfigProvider;
use Magento\Quote\Model\Quote;

class Clicktopay extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_method_selected' => 'refresh',
        'payment_method_selected'  => 'refresh',
        'coupon_code_applied'      => 'refresh',
        'coupon_code_revoked'      => 'refresh',
    ];

    /**
     * Click to Pay transient token (mapped to additional_data by Hyvä on order placement)
     */
    public ?string $transient_token = null;

    /**
     * Click to Pay capture-context identifier (mapped to additional_data by Hyvä)
     */
    public ?string $identifier = null;

    public array $config = [];

    public array $grandTotal = [];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected MethodConfigProvider $methodConfigProvider;

    protected AssetRepository $assetRepo;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider,
        AssetRepository $assetRepo
    ) {
        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->methodConfigProvider = $methodConfigProvider;
        $this->assetRepo = $assetRepo;
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
     * Persist Drop-in payment data on the quote payment.
     */
    public function updatePaymentData(string $transientToken, string $identifier): string
    {
        try {
            $this->transient_token = $transientToken !== '' ? $transientToken : null;
            $this->identifier = $identifier;

            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('transient_token', $this->transient_token);
            $quote->getPayment()->setAdditionalInformation('identifier', $identifier);
            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return $transientToken;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if (empty($this->transient_token)) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Please complete Click to Pay before placing the order.');
        }

        return $resultFactory->createSuccess();
    }

    /**
     * URL of the shared Buckaroo SDK (includes ClickToPay Drop-in helpers).
     */
    public function getSdkUrl(): string
    {
        try {
            return $this->assetRepo->getUrl('Buckaroo_Magento2::js/lib/buckaroo-sdk.js');
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
            return '';
        }
    }

    /**
     * jQuery is required by buckaroo-sdk.js; loaded on demand for Click to Pay only.
     */
    public function getJqueryUrl(): string
    {
        try {
            return $this->assetRepo->getUrl('jquery/jquery.min.js');
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
            return '';
        }
    }

    private function getJsonConfig(): array
    {
        try {
            $config = $this->methodConfigProvider->getConfig();

            if (empty($config)) {
                return [];
            }

            if (!isset($config['payment']['buckaroo']['buckaroo_magento2_clicktopay'])) {
                return [];
            }

            return $config['payment']['buckaroo']['buckaroo_magento2_clicktopay'];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getGrandTotal(): array
    {
        $quote = $this->getQuote();
        if ($quote === null) {
            return [];
        }

        try {
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

    private function getQuote(): ?Quote
    {
        try {
            return $this->sessionCheckout->getQuote();
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return null;
    }
}
