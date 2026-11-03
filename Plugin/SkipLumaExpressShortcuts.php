<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Plugin;

use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Buckaroo\Magento2\Observer\AddExpressShortcuts;
use Magento\Framework\Event\Observer;

/**
 * The Magento 2 plugin adds its cart express buttons as Knockout components through the
 * shortcut_buttons_container event. Hyvä cannot run them and renders its own buttons
 * (buckaroo_hyvacheckout_paypal_express_cart layout), so they are skipped on Hyvä themes.
 */
class SkipLumaExpressShortcuts
{
    private IsHyvaTheme $isHyvaTheme;

    public function __construct(IsHyvaTheme $isHyvaTheme)
    {
        $this->isHyvaTheme = $isHyvaTheme;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(AddExpressShortcuts $subject, callable $proceed, Observer $observer)
    {
        if ($this->isHyvaTheme->execute()) {
            return null;
        }

        return $proceed($observer);
    }
}
