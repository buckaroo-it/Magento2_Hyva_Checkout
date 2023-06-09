<?php

namespace Buckaroo\HyvaCheckout\Model;

use Hyva\Checkout\Model\MethodMetaData as ModelMethodMetaData;
use Hyva\Checkout\Model\ConfigData\HyvaThemes\SystemConfigPayment;
use Hyva\Checkout\Model\MethodMetaData\IconRenderer;
use Hyva\Checkout\Model\MethodMetaData\SubtitleRenderer;
use Magento\Payment\Model\MethodInterface as PaymentMethodInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\View\Asset\Repository;

class MethodMetaData extends ModelMethodMetaData
{
    protected Repository $assetRepository;

    public function __construct(
        IconRenderer $iconRenderer,
        SubtitleRenderer $subtitleRenderer,
        LoggerInterface $logger,
        PaymentMethodInterface $method,
        SystemConfigPayment $systemConfigPayment,
        Repository $assetRepository,
        array $data = []
    ) {
        parent::__construct($iconRenderer, $subtitleRenderer, $logger, $method, $systemConfigPayment, $data);
        $this->assetRepository = $assetRepository;
    }
    public function renderIcon(): string
    {
        $icon = parent::getData(self::ICON);

        if (!is_array($icon)) {
            return '';
        }
        $logoLink = $this->assetRepository->getUrl($icon['svg']);
        return '<img style="height:30px" src="' . $logoLink . '">';
    }
}
