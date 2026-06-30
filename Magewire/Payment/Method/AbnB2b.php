<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Buckaroo\Magento2\Model\ConfigProvider\Method\AbnB2b as AbnB2bConfigProvider;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magewirephp\Magewire\Component;

class AbnB2b extends Component implements EvaluationInterface
{
    protected AbnB2bConfigProvider $abnB2bConfigProvider;

    public function __construct(AbnB2bConfigProvider $abnB2bConfigProvider)
    {
        $this->abnB2bConfigProvider = $abnB2bConfigProvider;
    }

    public function getTooltipText(): string
    {
        return $this->abnB2bConfigProvider->getTooltipText();
    }

    public function getTooltipLink(): string
    {
        return $this->abnB2bConfigProvider->getTooltipLink();
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        return $resultFactory->createSuccess();
    }
}
