<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Framework\UrlInterface;
use Magewirephp\Magewire\Component;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Checkout\Model\Session as SessionCheckout;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;

/**
 * Voucher payment method on the Hyvä checkout.
 *
 * Vouchers are applied through the Buckaroo REST endpoint before the order is placed. The order
 * can only be placed once they cover the order total, and that is always read from the server.
 */
class Voucher extends Component\Form implements EvaluationInterface
{
    private const METHOD_CODE = 'buckaroo_magento2_voucher';

    private const SERVICE_CODE = 'buckaroovoucher';

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
     * Whether applied partial payments, at least one of them a voucher, cover the order total
     */
    public bool $canSubmit = false;

    protected UrlInterface $urlBuilder;

    protected SessionCheckout $sessionCheckout;

    protected PaymentGroupTransaction $groupTransaction;

    public function __construct(
        UrlInterface $urlBuilder,
        SessionCheckout $sessionCheckout,
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->sessionCheckout = $sessionCheckout;
        $this->groupTransaction = $groupTransaction;
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

        if ($this->canSubmit === false) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage(
                    (string)__('Your vouchers do not cover the order total yet. Apply a voucher for the remaining amount or choose another payment method.')
                );
        }

        return $resultFactory->createSuccess();
    }

    public function getAjaxUrl()
    {
        $storeCode = $this->sessionCheckout->getQuote()->getStore()->getCode();

        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_WEB])
            . 'rest/' . rawurlencode((string)$storeCode) . '/V1/buckaroo/voucher/apply';
    }

    /**
     * Kept for frontend code that still calls it. The value it is given is ignored: whether the
     * vouchers cover the order is read from the server.
     *
     * @param bool $canSubmit
     */
    public function setCanSubmit(bool $canSubmit): void
    {
        $this->canSubmit = $this->isFullyPaid();
    }

    /**
     * Refresh the price summary after a voucher was applied
     */
    public function refreshTotals(): void
    {
        $this->canSubmit = $this->isFullyPaid();
        $this->emit('payment_method_selected', ['method' => self::METHOD_CODE]);
    }

    private function isFullyPaid(): bool
    {
        $quote = $this->sessionCheckout->getQuote();
        $reservedOrderId = $quote->getReservedOrderId();
        if (!$reservedOrderId) {
            return false;
        }

        $alreadyPaid = (float)$this->groupTransaction->getAlreadyPaid($reservedOrderId);

        return $alreadyPaid > 0
            && $alreadyPaid >= round((float)$quote->getGrandTotal(), 2) - self::AMOUNT_TOLERANCE
            && $this->hasVoucherPayment($reservedOrderId);
    }

    /**
     * Whether a voucher is among the successful partial payments; giftcards alone are no voucher payment
     */
    private function hasVoucherPayment(string $reservedOrderId): bool
    {
        foreach ($this->groupTransaction->getGroupTransactionItems($reservedOrderId) as $item) {
            if ($item['servicecode'] === self::SERVICE_CODE) {
                return true;
            }
        }

        return false;
    }
}
