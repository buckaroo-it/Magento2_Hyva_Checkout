<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Framework\View\Asset\Repository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay as MethodConfigProvider;
use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;

class Applepay extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_method_selected' => 'refresh',
        'payment_method_selected' => 'refresh',
        'coupon_code_applied' => 'refresh',
        'coupon_code_revoked' => 'refresh'
    ];

    public ?string $encriptedData = null;

    public array $config = [];

    public array $totals = [];

    public array $grandTotal = [];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected MethodConfigProvider $methodConfigProvider;

    protected Repository $assetRepo;

    protected Log $logger;


    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider,
        Repository $assetRepo,
        Log $logger,
    ) {
        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->methodConfigProvider = $methodConfigProvider;
        $this->assetRepo = $assetRepo;
        $this->logger = $logger;
    }

    public function mount(): void
    {
        $this->config = $this->getJsonConfig();
        $this->totals = $this->getTotalLines();
        $this->grandTotal = $this->getGrandTotal();
    }

    public function hydrate()
    {
        $this->config = $this->getJsonConfig();
        $this->totals = $this->getTotalLines();
        $this->grandTotal = $this->getGrandTotal();
    }

    public function updateData(string $paymentData, string $billingContact)
    {
        try {
            $quote = $this->sessionCheckout->getQuote();
            $applePayEncoded = base64_encode($paymentData);
            $quote->getPayment()->setAdditionalInformation('applepayTransaction', $applePayEncoded);
            $quote->getPayment()->setAdditionalInformation('billingContact', $billingContact);

            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return $paymentData;
    }
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        try {
            $quote = $this->sessionCheckout->getQuote();
            $integrationMode = $this->methodConfigProvider->getIntegrationMode();

            if ($integrationMode) {
                $paymentData = $quote->getPayment()->getAdditionalInformation('applepayTransaction');

                if (empty($paymentData)) {
                    return $resultFactory->createErrorMessageEvent()
                        ->withCustomEvent('payment:method:error')
                        ->withMessage('Payment data is missing');
                }
            }
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return $resultFactory->createSuccess();
    }

    public function getIntegrationMode()
    {
        try {
            $this->logger->debug('Applepay integration mode: ' . $this->methodConfigProvider->getIntegrationMode());
            return $this->methodConfigProvider->getIntegrationMode();
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return false;
    }

    public function getJsSdkUrl()
    {
        try {
            return $this->assetRepo->getUrl('Buckaroo_HyvaCheckout::js/applepay.js');
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
    }


    private function getJsonConfig(): array
    {
        $config = $this->methodConfigProvider->getConfig();
        if(!isset($config['payment']['buckaroo']['applepay'])) {
            $this->dispatchErrorMessage('Cannot retrieved config');
        }
        return $config['payment']['buckaroo']['applepay'];
    }

    /**
     * Get list of totals
     *
     * @return array
     */
    private function getTotalLines(): array
    {
        $totals = [];
        $quote = $this->getQuote();
        if($quote === null) {
            return $totals;
        }
        $quote->collectTotals();
        foreach ($quote->getTotals() as $key => $total) {
            if($total->getData('value') != 0 && $key !== 'grand_total') {
                $amount = $total->getData('value');
                if($key === 'subtotal') {
                    $amount = $quote->getSubtotalWithDiscount();//for subtotal we get it with discounts
                }

                $totals[] = [
                    "label" => $total->getData('title'),
                    "amount" => $amount,
                    "type" => 'final',
                ];
            }
        }
        return $totals;
    }

    /**
     * Get grand total
     *
     * @return array
     */
    private function getGrandTotal(): array
    {
        $quote = $this->getQuote();
        if($quote === null) {
            return [];
        }
        if(!isset($quote->getTotals()['grand_total'])) {
            return [];
        }

        $total = $quote->getTotals()['grand_total'];

        return [
            "label" => $total->getData('title'),
            "amount" => $total->getData('value'),
            "type" => 'final',
        ];
    }

    /**
     * Get quote fro session
     *
     * @return Quote|null
     */
    private function getQuote() :?Quote
    {
        try {
           return $this->sessionCheckout->getQuote();
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return null;
    }
}
