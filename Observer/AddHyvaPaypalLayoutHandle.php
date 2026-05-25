<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Observer;

use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Layout;

/**
 * Adds Hyvä-only layout handles for PayPal Express and checkout success so Luma stores are unaffected.
 */
class AddHyvaPaypalLayoutHandle implements ObserverInterface
{
    private const PAGE_HANDLE_MAP = [
        'checkout_cart_index' => 'buckaroo_hyvacheckout_paypal_express_cart',
        'catalog_product_view' => 'buckaroo_hyvacheckout_paypal_express_product',
        'checkout_onepage_success' => 'hyva_checkout_onepage_success',
    ];

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

        /** @var Layout|null $layout */
        $layout = $observer->getEvent()->getData('layout');
        if (!$layout instanceof Layout) {
            return;
        }

        $update = $layout->getUpdate();
        $handles = $update->getHandles();

        foreach (self::PAGE_HANDLE_MAP as $pageHandle => $paypalHandle) {
            if (in_array($pageHandle, $handles, true)) {
                $update->addHandle($paypalHandle);
            }
        }
    }
}
