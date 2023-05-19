<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Framework\UrlInterface;
use Magewirephp\Magewire\Component;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;


class Voucher extends Component\Form implements EvaluationInterface
{
    public bool $canSubmit = false;

    protected UrlInterface $urlBuilder;

    public function __construct(UrlInterface $urlBuilder) {
        $this->urlBuilder = $urlBuilder;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->canSubmit === false) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Cannot complete payment with voucher');
        }

        return $resultFactory->createSuccess();
    }

    public function getAjaxUrl()
    {
        return $this->urlBuilder->getRouteUrl('rest/default/V1/buckaroo/voucher/')."apply";
    }

    public function setCanSubmit(bool $canSubmit): void
    {
        $this->canSubmit = $canSubmit;
    }
}
