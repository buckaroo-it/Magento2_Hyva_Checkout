<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Service;

use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay as ApplepayConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Ideal as IdealConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal as PaypalConfig;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Tells whether an express button can be shown on a page (Product|Cart), based on configuration only.
 *
 * Mirrors the config checks the button blocks run before rendering, so the layout only loads a method's
 * assets when its button can actually appear. The blocks keep their own runtime checks
 * (iDIN verification, cart contents).
 */
class ExpressButtonAvailability
{
    /**
     * @var PaypalConfig
     */
    private $paypalConfig;

    /**
     * @var ApplepayConfig
     */
    private $applepayConfig;

    /**
     * @var IdealConfig
     */
    private $idealConfig;

    /**
     * @var AccountConfig
     */
    private $accountConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        PaypalConfig $paypalConfig,
        ApplepayConfig $applepayConfig,
        IdealConfig $idealConfig,
        AccountConfig $accountConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->paypalConfig = $paypalConfig;
        $this->applepayConfig = $applepayConfig;
        $this->idealConfig = $idealConfig;
        $this->accountConfig = $accountConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Same check as PaypalExpress::canShowProductButton()/canShowCartButton().
     */
    public function isPaypalExpressAvailable(string $page): bool
    {
        try {
            return (bool)$this->paypalConfig->canShowButtonForPage($page, $this->storeManager->getStore());
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Same check as Applepay::canShowButton().
     */
    public function isApplePayExpressAvailable(string $page): bool
    {
        try {
            $status = $this->applepayConfig->getActive();
            if ($status != 1 && $status != 2) {
                return false;
            }

            if (!in_array($page, $this->applepayConfig->getAvailableButtons(), true)) {
                return false;
            }

            if (!$this->applepayConfig->isApplePayEnabled()) {
                return false;
            }

            $integrationMode = $this->applepayConfig->getIntegrationMode();

            return $integrationMode !== '1' && $integrationMode !== 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Same check as IdealFastCheckout::canShowButton().
     */
    public function isIdealFastCheckoutAvailable(string $page): bool
    {
        try {
            $status = $this->accountConfig->getActive();
            if ($status != 1 && $status != 2) {
                return false;
            }

            $store = $this->storeManager->getStore();

            return $this->idealConfig->isFastCheckoutEnabled($store)
                && $this->idealConfig->canShowButtonForPage($page, $store)
                && $this->idealConfig->isIDealEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
