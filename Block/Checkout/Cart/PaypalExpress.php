<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Block\Checkout\Cart;

use Buckaroo\Magento2\Block\Catalog\Product\View\PaypalExpress as PaypalExpressBase;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Cart page PayPal Express block. Extends Magento2 block and adds cart total for Hyva (no quote.totals JS).
 */
class PaypalExpress extends PaypalExpressBase
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal $paypalConfig,
        CheckoutSession $checkoutSession,
        ?\Magento\Framework\Registry $registry = null,
        array $data = []
    ) {
        parent::__construct($context, $configProviderAccount, $encryptor, $paypalConfig, $registry, $data);
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Cart total for PayPal amount. Uses quote grand_total (includes tax).
     *
     * @return float
     */
    public function getCartTotal(): float
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                return 0.01;
            }
            $quote->collectTotals();
            $value = (float) $quote->getGrandTotal();
            return $value > 0 ? round($value, 2) : 0.01;
        } catch (NoSuchEntityException $e) {
            return 0.01;
        }
    }

    /**
     * Check if current quote has one or more items.
     *
     * @return bool
     */
    public function hasCartItems(): bool
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                return false;
            }

            return (int) $quote->getItemsQty() > 0;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}
