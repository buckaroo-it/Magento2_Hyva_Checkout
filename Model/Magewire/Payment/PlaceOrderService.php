<?php

namespace Buckaroo\HyvaCheckout\Model\Magewire\Payment;

use Magento\Quote\Model\Quote;
use Composer\InstalledVersions;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartManagementInterface;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;

class PlaceOrderService extends AbstractPlaceOrderService
{
    private const COMPOSER_MODULE_NAME = 'buckaroo/magento2-hyva-checkout';

    protected Registry $registry;

    public function __construct(
        CartManagementInterface $cartManagement,
        Registry $registry
    ) {
        $this->registry = $registry;
        parent::__construct($cartManagement);
    }


    
    /**
     * @throws CouldNotSaveException
     */
    public function placeOrder(Quote $quote): int
    {
        $this->setPlatformInfo($quote);
        return parent::placeOrder($quote);
    }

    /**
     * Redirect to buckaroo payment engine
     *
     * @see https://docs.hyva.io/checkout/hyva-checkout/devdocs/payment-integration-api.html
     *
     * @param Quote $quote
     * @param int|null $orderId
     * @return string
     * @SuppressWarnings (PHPMD.UnusedFormalParameter)
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        if($this->hasRedirect()) {
            return $this->getResponse()->RequiredAction->RedirectURL;
        }
        
        // If payment was successful but no redirect is required (e.g., Riverty, direct payments)
        if($this->isSuccessfulPayment()) {
            return 'checkout/onepage/success';
        }
        
        return parent::getRedirectUrl($quote, $orderId);
    }

    private function getResponse()
    {
        if ($this->registry && $this->registry->registry('buckaroo_response')) {
            return $this->registry->registry('buckaroo_response')[0];
        }
    }

    private function hasRedirect(): bool
    {
        $response = $this->getResponse();
        return !empty($response->RequiredAction->RedirectURL);
    }

    private function isSuccessfulPayment(): bool
    {
        $response = $this->getResponse();
        if (!$response) {
            return false;
        }
        
        // Check if payment was successful (status code 190)
        return !empty($response->Status->Code->Code) && $response->Status->Code->Code == 190;
    }

    /**
     * Set platform info to send over
     *
     * @param Quote $quote
     *
     * @return void
     */
    private function setPlatformInfo(Quote $quote)
    {
        $version = 'unknown';

        if (InstalledVersions::isInstalled(self::COMPOSER_MODULE_NAME)) {
            $version = InstalledVersions::getVersion(self::COMPOSER_MODULE_NAME);
        }
        $quote->getPayment()->setAdditionalInformation(
            'buckaroo_platform_info',
            " / Hyva Checkout (".$version.")"
        );
    }
}
