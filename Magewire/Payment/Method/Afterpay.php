<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Session as SessionCheckout;
use Buckaroo\HyvaCheckout\Magewire\Payment\Method\AfterpayBase;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay as MethodConfigProvider;

class Afterpay extends AfterpayBase
{
    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider
    ) {
        parent::__construct($validator, $sessionCheckout, $quoteRepository, $methodConfigProvider);
    }
}
