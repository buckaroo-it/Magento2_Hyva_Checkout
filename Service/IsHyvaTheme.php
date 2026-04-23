<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Service;

use Magento\Framework\View\DesignInterface;

/**
 * Detects whether the currently active frontend theme belongs to the Hyvä family.
 *
 * The module is allowed to be enabled on stores that are not running Hyvä themes
 * (composer/sequence dependencies on Hyvä packages are optional here), so any
 * layout override, block swap, or template replacement the module performs MUST
 * be guarded with this service. Walks the theme inheritance chain looking for a
 * `hyva` substring in the theme code, mirroring the runtime check that
 * `Hyva\Theme\Service\CurrentTheme::isHyva()` performs when the package is present.
 */
class IsHyvaTheme
{
    /**
     * @var DesignInterface
     */
    private $design;

    /**
     * @var bool|null
     */
    private $cached;

    public function __construct(DesignInterface $design)
    {
        $this->design = $design;
    }

    /**
     * Return true when the active theme (or any ancestor) is a Hyvä theme.
     */
    public function execute(): bool
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        try {
            $theme = $this->design->getDesignTheme();
        } catch (\Throwable $e) {
            return $this->cached = false;
        }

        while ($theme !== null) {
            $code = (string)$theme->getCode();
            if ($code !== '' && stripos($code, 'hyva') !== false) {
                return $this->cached = true;
            }
            $theme = $theme->getParentTheme();
        }

        return $this->cached = false;
    }
}
