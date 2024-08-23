<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Block\Totals;

use Magento\Checkout\Model\Session as SessionCheckout;
use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\View\Element\Template\Context;

class Fee extends \Magento\Framework\View\Element\Template
{
    protected PaymentFee $feeHelper;

    protected SessionCheckout $sessionCheckout;

    public function __construct(
        Context         $context,
        array           $data,
        PaymentFee      $feeHelper,
        SessionCheckout $sessionCheckout
    )
    {
        parent::__construct($context, $data);
        $this->feeHelper = $feeHelper;
        $this->sessionCheckout = $sessionCheckout;
    }

    /**
     * Get title based on payment method config
     *
     * @return string
     */
    public function getTitle(): string
    {
        try {
            $payment = $this->sessionCheckout
                ->getQuote()
                ->getPayment();
            return $this->feeHelper->getBuckarooPaymentFeeLabel($payment->getMethod());
        } catch (\Throwable $th) {
            return __('Fee');
        }
    }

    /**
     * Get total from array of data
     *
     * @return float
     */
    public function getTotal(): float
    {
        $totalData = $this->getSegment();
        if (false === is_array($totalData)) {
            throw new \UnexpectedValueException('Expecting an array but getting '.gettype($totalData));
        }

        $extensionAttributes = $totalData['extension_attributes'];

        if (
            is_array($extensionAttributes) &&
            isset($extensionAttributes['buckaroo_fee']) &&
            is_scalar($extensionAttributes['buckaroo_fee'])
        ) {
            return floatval($extensionAttributes['buckaroo_fee']);
        }

        if ($extensionAttributes instanceof \Magento\Quote\Api\Data\TotalSegmentExtension) {
            /** @var \Magento\Quote\Api\Data\TotalSegmentExtension $extensionAttributes */
            if ($extensionAttributes->getBuckarooFee() !== null) {
                return $extensionAttributes->getBuckarooFee();
            }
        }

        return 0;
    }
}
