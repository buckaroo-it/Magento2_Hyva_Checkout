<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Block\Totals;

use Magento\Checkout\Model\Session as SessionCheckout;
use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\View\Element\Template\Context;

class Fee extends \Magento\Framework\View\Element\Template
{
    protected PaymentFee $feeHelper;

    public function __construct(
        Context         $context,
        array           $data,
        PaymentFee      $feeHelper,
    )
    {
        parent::__construct($context, $data);
        $this->feeHelper = $feeHelper;
    }

    /**
     * Get title based on payment method config
     *
     * @return string
     */
    public function getTitle(): string
    {
        try {
            return $this->feeHelper->getBuckarooPaymentFeeLabel();
        } catch (\Throwable $th) {
            return 'Payment Fee';
        }
    }

    /**
     * Get the fee segment, filling in the value the quote total does not expose itself
     *
     * @return array
     */
    public function getSegment(): array
    {
        $segment = $this->getData('segment');
        $segment = is_array($segment) ? $segment : [];

        $fee = $this->getFeeFromExtensionAttributes($segment['extension_attributes'] ?? null);

        $segment['code'] = $segment['code'] ?? 'buckaroo_fee';
        $segment['value'] = $fee ?? (float)($segment['value'] ?? 0);

        return $segment;
    }

    /**
     * @param mixed $extensionAttributes
     *
     * @return float|null
     */
    private function getFeeFromExtensionAttributes($extensionAttributes): ?float
    {
        if (is_array($extensionAttributes)) {
            $fee = $extensionAttributes['buckaroo_fee'] ?? null;
        } elseif (is_object($extensionAttributes) && method_exists($extensionAttributes, 'getBuckarooFee')) {
            $fee = $extensionAttributes->getBuckarooFee();
        } else {
            return null;
        }

        return is_numeric($fee) ? (float)$fee : null;
    }
}
