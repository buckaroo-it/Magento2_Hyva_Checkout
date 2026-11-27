<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Observer;

use Buckaroo\HyvaCheckout\Service\ExpressButtonAvailability;
use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Layout;

/**
 * Adds Hyvä-only layout handles for the express buttons and checkout success so Luma stores are unaffected.
 *
 * The JS/CSS of each express method lives in its own handle, added only when that method's button
 * can be shown on the page, so disabled methods do not load render-blocking assets in <head>.
 */
class AddHyvaPaypalLayoutHandle implements ObserverInterface
{
    private const PAGE_HANDLE_MAP = [
        'checkout_cart_index' => 'buckaroo_hyvacheckout_paypal_express_cart',
        'catalog_product_view' => 'buckaroo_hyvacheckout_paypal_express_product',
        'checkout_onepage_success' => 'hyva_checkout_onepage_success',
    ];

    /**
     * Page handle => express page name used by the payment configuration.
     */
    private const EXPRESS_PAGES = [
        'checkout_cart_index' => 'Cart',
        'catalog_product_view' => 'Product',
    ];

    public const HANDLE_STYLES = 'buckaroo_hyvacheckout_express_styles';
    public const HANDLE_PAYPAL_CART = 'buckaroo_hyvacheckout_express_paypal_cart';
    public const HANDLE_PAYPAL_PRODUCT = 'buckaroo_hyvacheckout_express_paypal_product';
    public const HANDLE_APPLEPAY = 'buckaroo_hyvacheckout_express_applepay';
    public const HANDLE_IDEAL_FAST_CHECKOUT = 'buckaroo_hyvacheckout_express_ideal_fast_checkout';

    /**
     * @var IsHyvaTheme
     */
    private $isHyvaTheme;

    /**
     * @var ExpressButtonAvailability
     */
    private $expressButtonAvailability;

    public function __construct(IsHyvaTheme $isHyvaTheme, ExpressButtonAvailability $expressButtonAvailability)
    {
        $this->isHyvaTheme = $isHyvaTheme;
        $this->expressButtonAvailability = $expressButtonAvailability;
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

        foreach (self::PAGE_HANDLE_MAP as $pageHandle => $layoutHandle) {
            if (!in_array($pageHandle, $handles, true)) {
                continue;
            }

            $update->addHandle($layoutHandle);

            if (isset(self::EXPRESS_PAGES[$pageHandle])) {
                foreach ($this->getExpressAssetHandles(self::EXPRESS_PAGES[$pageHandle]) as $assetHandle) {
                    $update->addHandle($assetHandle);
                }
            }
        }
    }

    /**
     * Asset handles for the express methods that can be shown on the given page.
     *
     * @return string[]
     */
    private function getExpressAssetHandles(string $page): array
    {
        $assetHandles = [];

        if ($this->expressButtonAvailability->isPaypalExpressAvailable($page)) {
            $assetHandles[] = $page === 'Cart' ? self::HANDLE_PAYPAL_CART : self::HANDLE_PAYPAL_PRODUCT;
        }

        if ($this->expressButtonAvailability->isApplePayExpressAvailable($page)) {
            $assetHandles[] = self::HANDLE_APPLEPAY;
        }

        if ($this->expressButtonAvailability->isIdealFastCheckoutAvailable($page)) {
            $assetHandles[] = self::HANDLE_IDEAL_FAST_CHECKOUT;
        }

        if ($assetHandles !== []) {
            array_unshift($assetHandles, self::HANDLE_STYLES);
        }

        return $assetHandles;
    }
}
