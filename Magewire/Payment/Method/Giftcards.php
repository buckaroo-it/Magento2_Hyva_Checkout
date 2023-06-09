<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\UrlInterface;
use Magewirephp\Magewire\Component;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Checkout\Model\Session as SessionCheckout;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface as GiftcardRequest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as MethodConfigProvider;

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

    public function __construct(
        UrlInterface $urlBuilder,
        SessionCheckout $sessionCheckout,
        PaymentGroupTransaction $groupTransaction,
        MethodConfigProvider $methodConfigProvider,
        GiftcardRequest $giftcardRequest,
        GiftcardResponse $giftcardResponse,
        Log $logger
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->sessionCheckout = $sessionCheckout;
        $this->groupTransaction = $groupTransaction;
        $this->methodConfigProvider = $methodConfigProvider;
        $this->giftcardRequest = $giftcardRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->logger = $logger;
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
        if ($this->canSubmit === false) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Cannot complete payment with voucher');
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
            !isset($config['avaibleGiftcards']) ||
            !is_array($config['avaibleGiftcards'])
        ) {
            return [];
        }
        return array_filter(
            $config['avaibleGiftcards'],
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
        if (isset($config['payment']['buckaroo'])) {
            return $config['payment']['buckaroo'];
        }
        return null;
    }

    /**
     * Do a partial payment request, update canSubmit if remanding amount 0,
     * emit `payment_method_selected` to update the totals
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
        try {
            $quote = $this->sessionCheckout->getQuote();
            $this->emit('payment_method_selected');
            $response = $this->getGiftcardResponse(
                $quote,
                $this->buildGiftcardRequest($quote, $card, $cardNumber, $pin)->send()
            );
            $this->emit("giftcard_response", $response);
        } catch (\Throwable $th) {
            $this->logger->addDebug((string)$th);
            $this->emit("giftcard_response", ["error" => __('Cannot apply giftcard')]);
        }
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
