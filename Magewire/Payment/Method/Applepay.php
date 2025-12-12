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
use Buckaroo\Magento2\Logging\Log as BuckarooLog;

class Applepay extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_method_selected' => 'refresh',
        'payment_method_selected' => 'refresh',
        'coupon_code_applied' => 'refresh',
        'coupon_code_revoked' => 'refresh'
    ];

    public ?string $encriptedData = null;

    public ?string $applepayTransaction = null;

    public ?string $billingContact = null;

    public array $config = [];

    public array $totals = [];

    public array $grandTotal = [];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected MethodConfigProvider $methodConfigProvider;

    protected Repository $assetRepo;

    protected BuckarooLog $logger;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider,
        Repository $assetRepo,
        BuckarooLog $logger
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
            $this->applepayTransaction = $paymentData;
            $this->billingContact = $billingContact;

            // Base64 encode the payment data (required by Buckaroo API)
            // This matches what assignData() does in the payment method
            $applepayEncoded = base64_encode($paymentData);

            // Save payment data to quote payment additional information
            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('applepayTransaction', $applepayEncoded);
            $quote->getPayment()->setAdditionalInformation('billingContact', $billingContact);
            
            $this->quoteRepository->save($quote);
            
            $this->logger->addDebug('[Apple Pay Hyva] Payment data saved to quote (base64 encoded)', [
                'payment_data_length' => strlen($paymentData),
                'encoded_length' => strlen($applepayEncoded),
                'has_billing_contact' => !empty($billingContact)
            ]);

        } catch (LocalizedException $exception) {
            $this->logger->addError('[Apple Pay] Failed to update payment data: ' . $exception->getMessage());
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return $paymentData;
    }
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        // Check if we're in client-side mode (SDK mode)
        $config = $this->getJsonConfig();
        $isClientSide = isset($config['integrationMode']) ? (bool) $config['integrationMode'] : false;
        
        // Only validate payment data in client-side/SDK mode
        if ($isClientSide && empty($this->applepayTransaction)) {
            $this->logger->addError('[Apple Pay Hyva] Payment data missing during order placement');
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Apple Pay payment data is missing. Please try again.');
        }
        
        return $resultFactory->createSuccess();
    }

    public function getJsSdkUrl(): string
    {
        try {
            return $this->assetRepo->getUrl('Buckaroo_HyvaCheckout::js/applepay.js');
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
            return '';
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
