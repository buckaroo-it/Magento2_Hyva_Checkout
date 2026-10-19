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

        $this->clearStoredTransaction();

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
     * @param string $cardNetwork
     * @return string
     */
    public function updateData(string $paymentData, string $billingContact, string $cardNetwork = ''): string
    {
        try {
            $this->applepayTransaction = $paymentData;
            $this->billingContact = $billingContact;

            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('applepayTransaction', $paymentData);
            $quote->getPayment()->setAdditionalInformation('billingContact', $billingContact);
            $quote->getPayment()->setAdditionalInformation(
                'applepayCardNetwork',
                $this->sanitizeCardNetwork($cardNetwork)
            );

            // Save the quote to persist the additional information
            $this->quoteRepository->save($quote);

        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return $paymentData;
    }

    /**
     * Keep only a plain card network name such as "Visa" or "MasterCard"
     *
     * @param string $cardNetwork
     *
     * @return string
     */
    private function sanitizeCardNetwork(string $cardNetwork): string
    {
        $cardNetwork = trim($cardNetwork);

        return preg_match('/^[A-Za-z][A-Za-z ]{0,31}$/', $cardNetwork) === 1 ? $cardNetwork : '';
    }

    /**
     * Evaluate completion
     *
     * In inline mode the order can only be placed once the Apple Pay sheet has returned its payment data
     * (stored by updateData()). In redirect mode the shopper completes Apple Pay at Buckaroo.
     *
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->isClientSideMode() && empty($this->getStoredTransaction())) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Apple Pay could not be completed. Please try again.');
        }

        return $resultFactory->createSuccess();
    }

    /**
     * Inline (SDK) mode: integrationMode 0. Redirect mode: integrationMode 1.
     *
     * @return bool
     */
    private function isClientSideMode(): bool
    {
        return !empty($this->config) && (string)($this->config['integrationMode'] ?? '1') === '0';
    }

    /**
     * Apple Pay payment data stored on the quote for this checkout
     *
     * @return string|null
     */
    private function getStoredTransaction(): ?string
    {
        try {
            $transaction = $this->sessionCheckout->getQuote()->getPayment()->getAdditionalInformation('applepayTransaction');
        } catch (\Exception $exception) {
            return null;
        }

        return is_string($transaction) && $transaction !== '' ? $transaction : null;
    }

    /**
     * Apple Pay payment data can be used once; start every checkout visit without it
     *
     * @return void
     */
    private function clearStoredTransaction(): void
    {
        if ($this->getStoredTransaction() === null) {
            return;
        }

        try {
            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->unsAdditionalInformation('applepayTransaction');
            $this->quoteRepository->save($quote);
        } catch (\Exception $exception) {
            return;
        }
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
                    'amount' => $this->formatAmount($amount),
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
            'amount' => $this->formatAmount($total->getData('value')),
            'type' => 'final',
        ];
    }

    /**
     * Amounts are kept as strings so the component data stays identical between requests
     * (a float such as 34.0 would come back from the browser as the integer 34)
     *
     * @param mixed $amount
     * @return string
     */
    private function formatAmount($amount): string
    {
        return number_format((float)$amount, 2, '.', '');
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
