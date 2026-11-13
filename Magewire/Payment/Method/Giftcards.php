<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Framework\UrlInterface;
use Magewirephp\Magewire\Component;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\PartialPayment\ApplyResult;
use Buckaroo\Magento2\Model\PartialPayment\ApplyService;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\GiftcardFactory;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentMethods;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentUnavailableException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as MethodConfigProvider;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

/**
 * Inline giftcards on the Hyvä checkout.
 *
 * Giftcards are applied through the Buckaroo partial payment service. Whether they cover the order
 * total is always read from the server.
 */
class Giftcards extends Component\Form implements EvaluationInterface
{
    private const METHOD_CODE = 'buckaroo_magento2_giftcards';

    /**
     * Amounts within this distance of each other count as equal
     */
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * Recompute whether the order is covered when the order total can change
     */
    protected $listeners = [
        'shipping_method_selected'   => 'refreshCoverage',
        'shipping_address_saved'     => 'refreshCoverage',
        'shipping_address_activated' => 'refreshCoverage',
        'billing_address_saved'      => 'refreshCoverage',
        'billing_address_activated'  => 'refreshCoverage',
        'coupon_code_applied'        => 'refreshCoverage',
        'coupon_code_revoked'        => 'refreshCoverage',
    ];

    /**
     * Whether applied partial payments, at least one of them a giftcard, cover the order total
     */
    public bool $canSubmit = false;

    protected UrlInterface $urlBuilder;

    protected SessionCheckout $sessionCheckout;

    protected PaymentGroupTransaction $groupTransaction;

    protected MethodConfigProvider $methodConfigProvider;

    protected Log $logger;

    protected $pricingHelper;

    private ApplyService $applyService;

    private GiftcardFactory $giftcardFactory;

    private PartialPaymentMethods $partialPaymentMethods;

    public function __construct(
        UrlInterface $urlBuilder,
        SessionCheckout $sessionCheckout,
        PaymentGroupTransaction $groupTransaction,
        MethodConfigProvider $methodConfigProvider,
        Log $logger,
        PricingHelper $pricingHelper,
        ApplyService $applyService,
        GiftcardFactory $giftcardFactory,
        PartialPaymentMethods $partialPaymentMethods
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->sessionCheckout = $sessionCheckout;
        $this->groupTransaction = $groupTransaction;
        $this->methodConfigProvider = $methodConfigProvider;
        $this->logger = $logger;
        $this->pricingHelper = $pricingHelper;
        $this->applyService = $applyService;
        $this->giftcardFactory = $giftcardFactory;
        $this->partialPaymentMethods = $partialPaymentMethods;
    }

    public function mount(): void
    {
        $this->canSubmit = $this->isFullyPaid();
    }

    /**
     * Recompute from the server whether the order is covered
     */
    public function refreshCoverage(): void
    {
        $this->canSubmit = $this->isFullyPaid();
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        $this->canSubmit = $this->isFullyPaid();

        if ($this->canSubmit === false && !$this->isRedirect()) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Cannot complete payment with giftcards');
        }

        return $resultFactory->createSuccess();
    }

    /**
     * Get giftcard issuers
     *
     * @return array
     */
    public function getGiftcardIssuers(): array
    {
        $config = $this->getConfig();
        if (
            $config === null ||
            !isset($config['availableGiftcards']) ||
            !is_array($config['availableGiftcards'])
        ) {
            return [];
        }
        return array_filter(
            $config['availableGiftcards'],
            function ($type) {
                return isset($type['code']) &&
                    isset($type['title']);
            }
        );
    }

    public function isRedirect(): bool
    {
        $config = $this->getConfig();

        return $config === null ||
            !isset($config['groupGiftcards']) ||
            $config['groupGiftcards'] == 1 ||
            count($this->getGiftcardIssuers()) === 0;
    }

    private function getConfig(): ?array
    {
        $config = $this->methodConfigProvider->getConfig();

        return $config['payment']['buckaroo']['buckaroo_magento2_giftcards'] ?? null;
    }

    /**
     * Apply a giftcard through the partial payment service and send the outcome to the frontend
     *
     * @param string $card
     * @param string $cardNumber
     * @param string $pin
     *
     * @return void
     */
    public function applyGiftcard(
        string $card,
        string $cardNumber,
        string $pin
    ): void {
        if ($this->isRedirect()) {
            $this->emitGiftcardError(__('Cannot apply giftcard'));
            return;
        }

        try {
            $result = $this->applyService->apply(
                $this->sessionCheckout->getQuote(),
                $this->giftcardFactory->create([
                    'cardId'     => $card,
                    'cardNumber' => $cardNumber,
                    'pin'        => $pin,
                ])
            );
        } catch (PartialPaymentRejectedException|PartialPaymentUnavailableException $e) {
            $this->emitGiftcardError($e->getMessage());
            return;
        } catch (\Throwable $th) {
            // Class and message only: a stack trace can carry the card number and PIN as call arguments
            $this->logger->addError(sprintf(
                '[Giftcard] | [Hyva] | [%s:%s] - Apply Inline Giftcard | [ERROR]: %s: %s',
                __METHOD__,
                __LINE__,
                get_class($th),
                $th->getMessage()
            ));
            $this->emitGiftcardError(
                __('The giftcard could not be applied. Please check the card number and PIN and try again.')
            );
            return;
        }

        $this->canSubmit = $this->isFullyPaid();
        // Refresh the price summary so the paid amount shows at once
        $this->emit('payment_method_selected', ['method' => self::METHOD_CODE]);

        $this->sendResponse($this->createResponse($result));
    }

    /**
     * Send a giftcard error to the frontend
     *
     * @param \Magento\Framework\Phrase|string $message
     *
     * @return void
     */
    private function emitGiftcardError($message): void
    {
        $this->sendResponse(['error' => (string)$message]);
    }

    /**
     * Send the giftcard response to the frontend as a browser event.
     *
     * A browser event reaches the page-wide listener also after the payment method was switched
     * and the component rendered again; a Magewire listener registered on the old component would not.
     *
     * @param array $response
     *
     * @return void
     */
    private function sendResponse(array $response): void
    {
        $this->dispatchBrowserEvent('buckaroo-giftcard-response', $response);
    }

    /**
     * Convert and format price value for current store
     *
     * @param float $price
     * @return float|string
     */
    public function getFormattedPrice($price)
    {
        return $this->pricingHelper->currency($price, true, false);
    }

    public function getRemainingAmount()
    {
        $quote = $this->sessionCheckout->getQuote();

        $grandTotal = round(floatval($quote->getGrandTotal()), 2);

        // Get the amount already paid through group transactions
        $alreadyPaid = $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId());

        // Calculate the remaining amount
        $remainingAmount = $grandTotal - $alreadyPaid;

        // Ensure the remaining amount is never negative
        $this->emit("remainingAmount", $this->getFormattedPrice($remainingAmount));
    }
    /**
     * Response for an accepted giftcard, in the shape the giftcard component reads
     *
     * @param ApplyResult $result
     *
     * @return array
     */
    private function createResponse(ApplyResult $result): array
    {
        if ($result->isFullyPaid()) {
            $message = __('You paid successfully.');
            $buttonMessage = '';
        } else {
            $message = __(
                'A partial payment of %1 %2 was successfully performed on a requested amount. Remainder amount %3 %4',
                $result->getCurrency(),
                $result->getAmountDebit(),
                $result->getRemainderAmount(),
                $result->getCurrency()
            );
            $buttonMessage = __('Pay remaining amount: %1 %2', $result->getRemainderAmount(), $result->getCurrency());
        }

        return [
            'remainder_amount_currency' => $this->getFormattedPrice($result->getRemainderAmount()),
            'remainder_amount'          => $result->getRemainderAmount(),
            'already_paid'              => $result->getAlreadyPaid(),
            'remaining_amount_message'  => (string)$buttonMessage,
            'message'                   => (string)$message,
        ];
    }

    /**
     * Whether applied partial payments cover the order total and at least one of them is a giftcard
     */
    private function isFullyPaid(): bool
    {
        $quote = $this->sessionCheckout->getQuote();
        $reservedOrderId = $quote->getReservedOrderId();
        if (!$reservedOrderId) {
            return false;
        }

        $alreadyPaid = (float)$this->groupTransaction->getAlreadyPaid($reservedOrderId);
        if ($alreadyPaid <= 0
            || $alreadyPaid < round((float)$quote->getGrandTotal(), 2) - self::AMOUNT_TOLERANCE
        ) {
            return false;
        }

        foreach ($this->groupTransaction->getGroupTransactionItems($reservedOrderId) as $item) {
            $method = $this->partialPaymentMethods->find($item['servicecode']);
            if ($method !== null && $method['type'] === PartialPaymentMethods::TYPE_GIFTCARD) {
                return true;
            }
        }

        return false;
    }
}
