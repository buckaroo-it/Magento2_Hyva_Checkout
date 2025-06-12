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
}
