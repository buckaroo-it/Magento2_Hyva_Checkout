<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Plugin;

use Buckaroo\HyvaCheckout\Plugin\SkipLumaExpressShortcuts;
use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Buckaroo\Magento2\Observer\AddExpressShortcuts;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

/**
 * The Knockout cart express buttons of the Magento 2 plugin are only added on Luma themes.
 */
class SkipLumaExpressShortcutsTest extends TestCase
{
    private function proceedsOn(bool $hyva): bool
    {
        $isHyva = $this->createMock(IsHyvaTheme::class);
        $isHyva->method('execute')->willReturn($hyva);

        $called = false;
        (new SkipLumaExpressShortcuts($isHyva))->aroundExecute(
            $this->createMock(AddExpressShortcuts::class),
            function () use (&$called) {
                $called = true;
            },
            $this->createMock(Observer::class)
        );

        return $called;
    }

    public function testSkippedOnHyvaTheme(): void
    {
        $this->assertFalse($this->proceedsOn(true));
    }

    public function testAddedOnLumaTheme(): void
    {
        $this->assertTrue($this->proceedsOn(false));
    }
}
