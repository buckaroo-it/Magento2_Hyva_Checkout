<?php

namespace Buckaroo\HyvaCheckout\Model\Magewire\Payment;

use Magento\Quote\Model\Quote;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartManagementInterface;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;

class PlaceOrderService extends AbstractPlaceOrderService
{

    protected Registry $registry;

    public function __construct(
        CartManagementInterface $cartManagement,
        Registry $registry
    ) {
        $this->registry = $registry;
        parent::__construct($cartManagement);
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
}