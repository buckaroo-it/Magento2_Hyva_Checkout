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
        Context $context,
        array $data,
        PaymentFee $feeHelper,
        SessionCheckout $sessionCheckout
    ) {
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
            return $this->feeHelper->getBuckarooPaymentFeeLabel();
        } catch (\Throwable $th) {
            return 'Payment Fee';
        }
    }

    /**
     * Get segment data for the fee
     *
     * @return array
     */
    public function getSegment(): array
    {
        $quote = $this->sessionCheckout->getQuote();
        $fee = $quote->getBuckarooFee();

        return [
            'code' => 'buckaroo_fee',
            'title' => $this->getTitle(),
            'value' => $fee ? (float)$fee : 0
        ];
    }
}
