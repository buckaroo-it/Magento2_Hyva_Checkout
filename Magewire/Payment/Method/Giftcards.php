<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\UrlInterface;
use Magewirephp\Magewire\Component;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Service\Giftcard\AttemptLimit;
use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface as GiftcardRequest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as MethodConfigProvider;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class Giftcards extends Component\Form implements EvaluationInterface
{
    public bool $canSubmit = false;

    protected UrlInterface $urlBuilder;

    protected SessionCheckout $sessionCheckout;

    protected PaymentGroupTransaction $groupTransaction;

    protected MethodConfigProvider $methodConfigProvider;

    protected GiftcardRequest $giftcardRequest;

    protected GiftcardResponse $giftcardResponse;

    protected Log $logger;
    protected $pricingHelper;

    protected AttemptLimit $attemptLimit;

    public function __construct(
        UrlInterface $urlBuilder,
        SessionCheckout $sessionCheckout,
        PaymentGroupTransaction $groupTransaction,
        MethodConfigProvider $methodConfigProvider,
        GiftcardRequest $giftcardRequest,
        GiftcardResponse $giftcardResponse,
        Log $logger,
        PricingHelper $pricingHelper,
        AttemptLimit $attemptLimit
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->sessionCheckout = $sessionCheckout;
        $this->groupTransaction = $groupTransaction;
        $this->methodConfigProvider = $methodConfigProvider;
        $this->giftcardRequest = $giftcardRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->logger = $logger;
        $this->pricingHelper = $pricingHelper;
        $this->attemptLimit = $attemptLimit;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $quote = $this->sessionCheckout->getQuote();
        $this->canSubmit = abs(
            $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId()) - round(floatval($quote->getGrandTotal()), 2)
        ) < 0.05;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {

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
     * Do a partial payment request, update canSubmit if remainder amount is 0,
     * emit `giftcard_response` with the response
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
            $quote = $this->sessionCheckout->getQuote();
            $this->attemptLimit->assertWithinLimit($quote);

            try {
                $buckarooResponse = $this->buildGiftcardRequest($quote, $card, $cardNumber, $pin)->send();
            } catch (ApiException $apiException) {
                $this->attemptLimit->registerFailedAttempt($quote);
                throw $apiException;
            }

            $response = $this->getGiftcardResponse($quote, $buckarooResponse);

            if (isset($response['error'])) {
                $this->attemptLimit->registerFailedAttempt($quote);
            }

            if (isset($response['remainder_amount']) && $response['remainder_amount'] == 0) {
                $this->canSubmit = true;
            }

            try {
                $this->emit("giftcard_response", $response);
            } catch (\Throwable $emitError) {
                $this->logger->addDebug('Error emitting giftcard_response: ' . $emitError->getMessage());
            }
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[Giftcard] | [Hyva] | [%s:%s] - Apply Inline Giftcard | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
            $this->emitGiftcardError(__('Cannot apply giftcard'));
        }
    }

    /**
     * Emit a generic giftcard error to the frontend
     *
     * @param \Magento\Framework\Phrase|string $message
     *
     * @return void
     */
    private function emitGiftcardError($message): void
    {
        try {
            $this->emit("giftcard_response", ["error" => $message]);
        } catch (\Throwable $emitError) {
            $this->logger->addDebug('Error emitting error response: ' . $emitError->getMessage());
        }
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
    protected function getGiftcardResponse(Quote $quote, $response)
    {
        $this->giftcardResponse->set($response, $quote);

        if ($this->giftcardResponse->getErrorMessage() !== null) {
            return ["error" => $this->giftcardResponse->getErrorMessage()];
        }

        $remainingAmount = $this->giftcardResponse->getRemainderAmount();

        if ($remainingAmount == 0) {
            $this->canSubmit = true;
        }

        $buttonMessage = '';
        $textMessage = __("Your paid successfully. Please finish your order");

        if ($remainingAmount > 0) {
            $textMessage = __(
                'A partial payment of %1 %2 was successfully performed on a requested amount. Remainder amount %3 %4',
                $this->giftcardResponse->getCurrency(),
                $this->giftcardResponse->getAmountDebit(),
                $this->giftcardResponse->getRemainderAmount(),
                $this->giftcardResponse->getCurrency()
            );

            $buttonMessage = __(
                'Pay remaining amount: %1 %2',
                $remainingAmount,
                $this->giftcardResponse->getCurrency()
            );
        }
        return [
            'remainder_amount_currency' => $this->getFormattedPrice($remainingAmount),
            'remainder_amount' => $remainingAmount,
            'already_paid' => $this->giftcardResponse->getAlreadyPaid($quote),
            'remaining_amount_message' => $buttonMessage,
            'message' => $textMessage
        ];
    }
    /**
     * Build giftcard request
     *
     * @param Quote $quote
     * @param string $card
     * @param string $cardNumber
     * @param string $pin
     *
     * @return GiftcardRequest
     */
    protected function buildGiftcardRequest(
        Quote $quote,
        string $card,
        string $cardNumber,
        string $pin
    ) {
        return $this->giftcardRequest
            ->setCardId($card)
            ->setCardNumber($cardNumber)
            ->setPin($pin)
            ->setQuote($quote);
    }
}
