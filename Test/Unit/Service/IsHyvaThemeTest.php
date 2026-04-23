<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Service;

use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use PHPUnit\Framework\TestCase;

/**
 * Guards module behaviour so it only touches layout when the active theme is Hyvä.
 * Exercises the theme-chain walk and the early-return paths that kept the module
 * from breaking Luma stores when Buckaroo_HyvaCheckout was mistakenly enabled.
 */
class IsHyvaThemeTest extends TestCase
{
    public function testReturnsTrueWhenActiveThemeCodeContainsHyva(): void
    {
        $theme = $this->makeTheme('Hyva/default');
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);

        self::assertTrue((new IsHyvaTheme($design))->execute());
    }

    public function testReturnsTrueWhenParentThemeIsHyva(): void
    {
        $hyvaParent = $this->makeTheme('Hyva/default');
        $child = $this->makeTheme('Acme/storefront', $hyvaParent);
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($child);

        self::assertTrue((new IsHyvaTheme($design))->execute());
    }

    public function testReturnsFalseForLumaDescendantChain(): void
    {
        $luma = $this->makeTheme('Magento/luma');
        $blank = $this->makeTheme('Magento/blank', $luma);
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($blank);

        self::assertFalse((new IsHyvaTheme($design))->execute());
    }

    public function testReturnsFalseAndCachesWhenDesignThrows(): void
    {
        $design = $this->createMock(DesignInterface::class);
        $design->expects(self::once())
            ->method('getDesignTheme')
            ->willThrowException(new \RuntimeException('design not ready'));

        $service = new IsHyvaTheme($design);

        self::assertFalse($service->execute());
        self::assertFalse($service->execute()); // cached, no second call
    }

    public function testResultIsCachedAcrossCalls(): void
    {
        $theme = $this->makeTheme('Hyva/default');
        $design = $this->createMock(DesignInterface::class);
        $design->expects(self::once())->method('getDesignTheme')->willReturn($theme);

        $service = new IsHyvaTheme($design);

        self::assertTrue($service->execute());
        self::assertTrue($service->execute());
    }

    private function makeTheme(string $code, ?ThemeInterface $parent = null): ThemeInterface
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn($code);
        $theme->method('getParentTheme')->willReturn($parent);
        return $theme;
    }
}
