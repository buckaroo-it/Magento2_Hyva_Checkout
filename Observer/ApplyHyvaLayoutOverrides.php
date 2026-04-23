<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Observer;

use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;

/**
 * Applies Hyvä-specific block swaps only when the active theme is actually Hyvä.
 */
class ApplyHyvaLayoutOverrides implements ObserverInterface
{
    private const CART_LUMA_BLOCK = 'buckaroo.paypal.express.cart.button';
    private const CART_HYVA_BLOCK = 'buckaroo.paypal.express.cart.button.hyva';
    private const CART_HYVA_CLASS = \Buckaroo\HyvaCheckout\Block\Checkout\Cart\PaypalExpress::class;
    private const CART_HYVA_TEMPLATE = 'Buckaroo_HyvaCheckout::checkout/cart/paypal-express.phtml';
    private const CART_CONTAINER = 'content';

    private const PDP_BLOCK = 'buckaroo_magento2.product.info.paypal.express';
    private const PDP_HYVA_TEMPLATE = 'Buckaroo_HyvaCheckout::catalog/product/view/paypal-express.phtml';

    /**
     * @var IsHyvaTheme
     */
    private $isHyvaTheme;

    public function __construct(IsHyvaTheme $isHyvaTheme)
    {
        $this->isHyvaTheme = $isHyvaTheme;
    }

    public function execute(Observer $observer): void
    {
        if (!$this->isHyvaTheme->execute()) {
            return;
        }

        /** @var LayoutInterface|null $layout */
        $layout = $observer->getEvent()->getLayout();
        if (!$layout instanceof LayoutInterface) {
            return;
        }

        $handles = $layout->getUpdate()->getHandles();

        if (in_array('checkout_cart_index', $handles, true)) {
            $this->applyCartOverride($layout);
        }

        if (in_array('catalog_product_view', $handles, true)) {
            $this->applyPdpOverride($layout);
        }
    }

    /**
     * Replace the Luma cart PayPal block with the Hyvä variant inside the main content container.
     */
    private function applyCartOverride(LayoutInterface $layout): void
    {
        $lumaBlock = $layout->getBlock(self::CART_LUMA_BLOCK);
        if ($lumaBlock !== false) {
            $layout->unsetElement(self::CART_LUMA_BLOCK);
        }

        if ($layout->getBlock(self::CART_HYVA_BLOCK) !== false) {
            return;
        }

        $hyvaBlock = $layout->createBlock(
            self::CART_HYVA_CLASS,
            self::CART_HYVA_BLOCK,
            ['data' => ['template' => self::CART_HYVA_TEMPLATE]]
        );

        if (!$layout->hasElement(self::CART_CONTAINER)) {
            return;
        }

        $layout->setChild(self::CART_CONTAINER, $hyvaBlock->getNameInLayout(), self::CART_HYVA_BLOCK);
    }

    /**
     * Repoint the shared Luma PDP PayPal block at the Hyvä template.
     */
    private function applyPdpOverride(LayoutInterface $layout): void
    {
        $block = $layout->getBlock(self::PDP_BLOCK);
        if ($block === false) {
            return;
        }

        $block->setTemplate(self::PDP_HYVA_TEMPLATE);
    }
}
