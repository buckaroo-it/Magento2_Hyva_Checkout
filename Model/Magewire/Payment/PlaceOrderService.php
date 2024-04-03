<?php

namespace Buckaroo\HyvaCheckout\Model\Magewire\Payment;

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Buckaroo\Magento2\Model\Giftcard\Api\TransactionResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Composer\InstalledVersions;
use Magento\Quote\Api\CartManagementInterface;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;

class PlaceOrderService extends AbstractPlaceOrderService
{
    private const COMPOSER_MODULE_NAME = 'buckaroo/magento2-hyva-checkout';

    /**
     * @var BuckarooResponseDataInterface
     */
    private BuckarooResponseDataInterface $buckarooResponseData;

    /**
     * @var TransactionResponse|null
     */
    private ?TransactionResponse $buckarooResponse = null;

    public function __construct(
        CartManagementInterface $cartManagement,
        BuckarooResponseDataInterface $buckarooResponseData,
    ) {
        $this->buckarooResponseData = $buckarooResponseData;
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
        if($this->getResponse()->hasRedirect()) {
            return $this->getResponse()->getRedirectUrl();
        }
        return parent::getRedirectUrl($quote, $orderId);
    }

    /**
     * @return \Buckaroo\Transaction\Response\TransactionResponse|void
     */
    private function getResponse()
    {
        if (!$this->buckarooResponse) {
            $this->buckarooResponse = $this->buckarooResponseData->getResponse();
        }
        return $this->buckarooResponse;
    }

    /**
     * Set platform info to send over
     *
     * @param Quote $quote
     *
     * @return void
     * @throws LocalizedException
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
