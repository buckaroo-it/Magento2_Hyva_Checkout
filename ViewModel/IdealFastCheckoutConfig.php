<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\ViewModel;

use Buckaroo\Magento2\Block\Catalog\Product\View\IdealFastCheckout;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Config for the Hyvä iDEAL Fast Checkout button on the product and cart page.
 * The Magento 2 checkout version of this button is a Knockout component, which Hyvä cannot run.
 */
class IdealFastCheckoutConfig implements ArgumentInterface
{
    private CheckoutSession $checkoutSession;

    private UrlInterface $urlBuilder;

    private StoreManagerInterface $storeManager;

    public function __construct(
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
    }

    /**
     * Whether the button should be rendered for this page
     *
     * @param IdealFastCheckout $block
     * @param string            $page Product|Cart
     */
    public function canShow(IdealFastCheckout $block, string $page): bool
    {
        try {
            if (!$block->canShowButton($page) || $block->isIdinVerificationRequired()) {
                return false;
            }

            return $page !== 'Cart' || (float)$this->checkoutSession->getQuote()->getItemsCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param IdealFastCheckout $block
     * @param string            $page Product|Cart
     *
     * @return array{page: string, buttonVariant: string, restUrl: string, successUrl: string}
     */
    public function getConfig(IdealFastCheckout $block, string $page): array
    {
        $storeCode = (string)$this->storeManager->getStore()->getCode();

        return [
            'page'          => $page === 'Cart' ? 'cart' : 'product',
            'buttonVariant' => $block->getButtonVariant(),
            'restUrl'       => $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_WEB])
                . 'rest/' . rawurlencode($storeCode) . '/V1/',
            'successUrl'    => $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true]),
        ];
    }
}
