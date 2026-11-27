<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Observer;

use Buckaroo\HyvaCheckout\Observer\AddHyvaPaypalLayoutHandle;
use Buckaroo\HyvaCheckout\Service\ExpressButtonAvailability;
use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\ProcessorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Ensures Hyvä express layout handles are only added on Hyvä themes, and express assets only for enabled methods.
 */
class AddHyvaPaypalLayoutHandleTest extends TestCase
{
    private const HANDLE_CART = 'buckaroo_hyvacheckout_paypal_express_cart';
    private const HANDLE_PRODUCT = 'buckaroo_hyvacheckout_paypal_express_product';
    private const HANDLE_SUCCESS = 'hyva_checkout_onepage_success';

    public function testNoopWhenThemeIsNotHyva(): void
    {
        $isHyvaTheme = $this->createMock(IsHyvaTheme::class);
        $isHyvaTheme->method('execute')->willReturn(false);

        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::never())->method('addHandle');

        $layout = $this->createLayout(['checkout_cart_index'], $processor);

        (new AddHyvaPaypalLayoutHandle($isHyvaTheme, $this->availability()))->execute($this->makeObserver($layout));
    }

    public function testAddsCartHandleOnHyvaCartPage(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::once())->method('addHandle')->with(self::HANDLE_CART);

        $layout = $this->createLayout(['checkout_cart_index'], $processor);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $this->availability()))->execute($this->makeObserver($layout));
    }

    public function testAddsProductHandleOnHyvaProductPage(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::once())->method('addHandle')->with(self::HANDLE_PRODUCT);

        $layout = $this->createLayout(['catalog_product_view'], $processor);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $this->availability()))->execute($this->makeObserver($layout));
    }

    public function testAddsSuccessHandleOnHyvaCheckoutSuccessPage(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::once())->method('addHandle')->with(self::HANDLE_SUCCESS);

        $layout = $this->createLayout(['checkout_onepage_success'], $processor);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $this->availability()))->execute($this->makeObserver($layout));
    }

    public function testIgnoresUnrelatedLayoutHandles(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects(self::never())->method('addHandle');

        $layout = $this->createLayout(['cms_page_view'], $processor);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $this->availability()))->execute($this->makeObserver($layout));
    }

    public function testAddsAssetHandlesOnlyForEnabledMethodsOnProductPage(): void
    {
        $added = [];
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('addHandle')->willReturnCallback(function ($handle) use (&$added, $processor) {
            $added[] = $handle;
            return $processor;
        });

        $layout = $this->createLayout(['catalog_product_view'], $processor);
        $availability = $this->availability(['paypal' => true, 'applepay' => false, 'ideal' => false]);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $availability))->execute($this->makeObserver($layout));

        self::assertSame([
            self::HANDLE_PRODUCT,
            AddHyvaPaypalLayoutHandle::HANDLE_STYLES,
            AddHyvaPaypalLayoutHandle::HANDLE_PAYPAL_PRODUCT,
        ], $added);
    }

    public function testAddsAssetHandlesOnlyForEnabledMethodsOnCartPage(): void
    {
        $added = [];
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('addHandle')->willReturnCallback(function ($handle) use (&$added, $processor) {
            $added[] = $handle;
            return $processor;
        });

        $layout = $this->createLayout(['checkout_cart_index'], $processor);
        $availability = $this->availability(['paypal' => false, 'applepay' => true, 'ideal' => true]);

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $availability))->execute($this->makeObserver($layout));

        self::assertSame([
            self::HANDLE_CART,
            AddHyvaPaypalLayoutHandle::HANDLE_STYLES,
            AddHyvaPaypalLayoutHandle::HANDLE_APPLEPAY,
            AddHyvaPaypalLayoutHandle::HANDLE_IDEAL_FAST_CHECKOUT,
        ], $added);
    }

    public function testChecksAvailabilityForTheMatchingExpressPage(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $layout = $this->createLayout(['checkout_cart_index'], $processor);

        $availability = $this->createMock(ExpressButtonAvailability::class);
        $availability->expects(self::once())->method('isPaypalExpressAvailable')->with('Cart');
        $availability->expects(self::once())->method('isApplePayExpressAvailable')->with('Cart');
        $availability->expects(self::once())->method('isIdealFastCheckoutAvailable')->with('Cart');

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $availability))->execute($this->makeObserver($layout));
    }

    public function testSilentWhenObserverEventHasNoLayout(): void
    {
        $event = new DataObject();
        $observer = new Observer();
        $observer->setEvent($event);

        $this->expectNotToPerformAssertions();

        (new AddHyvaPaypalLayoutHandle($this->hyvaTheme(), $this->availability()))->execute($observer);
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

    /**
     * @param array<string, bool> $enabled
     * @return ExpressButtonAvailability&MockObject
     */
    private function availability(array $enabled = [])
    {
        $service = $this->createMock(ExpressButtonAvailability::class);
        $service->method('isPaypalExpressAvailable')->willReturn($enabled['paypal'] ?? false);
        $service->method('isApplePayExpressAvailable')->willReturn($enabled['applepay'] ?? false);
        $service->method('isIdealFastCheckoutAvailable')->willReturn($enabled['ideal'] ?? false);

        return $service;
    }
}
