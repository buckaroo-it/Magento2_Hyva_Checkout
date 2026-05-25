<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Observer;

use Buckaroo\HyvaCheckout\Observer\AddHyvaPaypalLayoutHandle;
use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\ProcessorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Ensures Hyvä PayPal Express layout handles are only added on Hyvä themes.
 */
class AddHyvaPaypalLayoutHandleTest extends TestCase
{
    private const HANDLE_CART = 'buckaroo_hyvacheckout_paypal_express_cart';
    private const HANDLE_PRODUCT = 'buckaroo_hyvacheckout_paypal_express_product';

    public function testNoopWhenThemeIsNotHyva(): void
    {
        $isHyvaTheme = $this->createMock(IsHyvaTheme::class);
        $isHyvaTheme->method('execute')->willReturn(false);

        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::never())->method('addHandle');

        $layout = $this->createLayout(['checkout_cart_index'], $processor);

        (new AddHyvaPaypalLayoutHandle($isHyvaTheme))->execute($this->makeObserver($layout));
    }

    public function testAddsCartHandleOnHyvaCartPage(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::once())->method('addHandle')->with(self::HANDLE_CART);

        $layout = $this->createLayout(['checkout_cart_index'], $processor);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testAddsProductHandleOnHyvaProductPage(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::once())->method('addHandle')->with(self::HANDLE_PRODUCT);

        $layout = $this->createLayout(['catalog_product_view'], $processor);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testIgnoresUnrelatedLayoutHandles(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::never())->method('addHandle');

        $layout = $this->createLayout(['cms_page_view'], $processor);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testSilentWhenObserverEventHasNoLayout(): void
    {
        $event = new DataObject();
        $observer = new Observer();
        $observer->setEvent($event);

        $this->expectNotToPerformAssertions();

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme()))->execute($observer);
    }

    /**
     * @return Layout&MockObject
     */
    private function createLayout(array $handles, ProcessorInterface $processor)
    {
        $processor->method('getHandles')->willReturn($handles);

        $layout = $this->createMock(Layout::class);
        $layout->method('getUpdate')->willReturn($processor);

        return $layout;
    }

    /**
     * @param Layout&MockObject $layout
     */
    private function makeObserver($layout): Observer
    {
        $event = new DataObject(['layout' => $layout]);
        $observer = new Observer();
        $observer->setEvent($event);

        return $observer;
    }

    /**
     * @return IsHyvaTheme&MockObject
     */
    private function hyvaTheme()
    {
        $service = $this->createMock(IsHyvaTheme::class);
        $service->method('execute')->willReturn(true);

        return $service;
    }
}
