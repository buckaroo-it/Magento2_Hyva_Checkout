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

class Applepay extends Component\Form implements EvaluationInterface
{
    protected $listeners = [
        'shipping_method_selected' => 'refresh',
        'payment_method_selected' => 'refresh',
        'coupon_code_applied' => 'refresh',
        'coupon_code_revoked' => 'refresh'
    ];

    /**
     * Apple Pay transaction data (will be automatically mapped to additional_data by Hyvä)
     */
    public ?string $applepayTransaction = null;

    /**
     * Billing contact data (will be automatically mapped to additional_data by Hyvä)
     */
    public ?string $billingContact = null;

    public array $config = [];

    public array $totals = [];

    public array $grandTotal = [];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected MethodConfigProvider $methodConfigProvider;

    protected Repository $assetRepo;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider,
        Repository $assetRepo,
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

        if (empty($this->config)) {
            return;
        }

        $this->totals = $this->getTotalLines();
        $this->grandTotal = $this->getGrandTotal();
    }

    public function hydrate()
    {
        $this->config = $this->getJsonConfig();

        if (empty($this->config)) {
            return;
        }

        $this->totals = $this->getTotalLines();
        $this->grandTotal = $this->getGrandTotal();
    }

    /**
     * Update Apple Pay transaction and billing contact data
     *
     * @param string $paymentData
     * @param string $billingContact
     * @return string
     */
    public function updateData(string $paymentData, string $billingContact): string
    {
        try {
            $this->applepayTransaction = $paymentData;
            $this->billingContact = $billingContact;

            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('applepayTransaction', $paymentData);
            $quote->getPayment()->setAdditionalInformation('billingContact', $billingContact);

            // Save the quote to persist the additional information
            $this->quoteRepository->save($quote);

        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return $paymentData;
    }
    /**
     * Evaluate completion - allow order placement
     *
     * window.buckarooTask handles Apple Pay authorization before this is called
     *
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        return $resultFactory->createSuccess();
    }

    /**
     * Get Apple Pay JavaScript SDK URL
     *
     * @return string
     */
    public function getJsSdkUrl(): string
    {
        try {
            return $this->assetRepo->getUrl('Buckaroo_HyvaCheckout::js/applepay.js');
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
            return '';
        }
    }


    /**
     * Get Apple Pay configuration from config provider
     *
     * @return array
     */
    private function getJsonConfig(): array
    {
        try {
            $config = $this->methodConfigProvider->getConfig();

            if (empty($config)) {
                return [];
            }

            if (!isset($config['payment']['buckaroo']['buckaroo_magento2_applepay'])) {
                return [];
            }

            return $config['payment']['buckaroo']['buckaroo_magento2_applepay'];
        } catch (\Exception $e) {
            return [];
        }
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
        if ($quote === null) {
            return $totals;
        }
        $quote->collectTotals();
        foreach ($quote->getTotals() as $key => $total) {
            if ($total->getData('value') != 0 && $key !== 'grand_total') {
                $amount = $total->getData('value');
                if ($key === 'subtotal') {
                    $amount = $quote->getSubtotalWithDiscount();
                }

                $totals[] = [
                    'label' => $total->getData('title'),
                    'amount' => $amount,
                    'type' => 'final',
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
        if ($quote === null) {
            return [];
        }
        if (!isset($quote->getTotals()['grand_total'])) {
            return [];
        }

        $total = $quote->getTotals()['grand_total'];

        return [
            'label' => $total->getData('title'),
            'amount' => $total->getData('value'),
            'type' => 'final',
        ];
    }

    /**
     * Get quote from session
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
        return null;
    }
}
