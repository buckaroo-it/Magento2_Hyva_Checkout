<?php

namespace Buckaroo\HyvaCheckout\Plugin;

use Hyva\Checkout\Model\MethodMetaData\IconRenderer as IconRendererHyva;
use Magento\Framework\View\Asset\Repository;

class IconRenderer
{
    protected Repository $assetRepository;

    public function __construct(
        Repository $assetRepository
    ) {
        $this->assetRepository = $assetRepository;
    }

    public function aroundRender(
        IconRendererHyva $subject,
        callable $proceed,
        array $icon
    ): string {
        // Check if this is a Buckaroo method and handle it
        if (isset($icon['svg']) && strpos($icon['svg'], 'Buckaroo_') !== false) {
            $logoLink = $this->assetRepository->getUrl($icon['svg']);
            return '<img style="height:30px" src="' . $logoLink . '">';
        }

        // Otherwise, proceed with the default behavior
        return $proceed($icon);
    }
}
