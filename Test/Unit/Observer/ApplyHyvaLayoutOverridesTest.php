<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Observer;

use Buckaroo\HyvaCheckout\Observer\ApplyHyvaLayoutOverrides;
use Buckaroo\HyvaCheckout\Service\IsHyvaTheme;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the theme guard and the cart/PDP block swaps performed when the active
 * theme is Hyvä. The observer used to live as raw layout XML under generic
 * handles, which broke PayPal Express on Luma; these tests lock in the gated
 * behaviour so the regression cannot reappear.
 */
class ApplyHyvaLayoutOverridesTest extends TestCase
{
    private const CART_LUMA_BLOCK = 'buckaroo.paypal.express.cart.button';
    private const CART_HYVA_BLOCK = 'buckaroo.paypal.express.cart.button.hyva';
    private const CART_HYVA_CLASS = \Buckaroo\HyvaCheckout\Block\Checkout\Cart\PaypalExpress::class;
    private const CART_HYVA_TEMPLATE = 'Buckaroo_HyvaCheckout::checkout/cart/paypal-express.phtml';
    private const CART_CONTAINER = 'content';
    private const PDP_BLOCK = 'buckaroo_magento2.product.info.paypal.express';
    private const PDP_HYVA_TEMPLATE = 'Buckaroo_HyvaCheckout::catalog/product/view/paypal-express.phtml';

    public function testNoopWhenThemeIsNotHyva(): void
    {
        $isHyvaTheme = $this->createMock(IsHyvaTheme::class);
        $isHyvaTheme->method('execute')->willReturn(false);

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::never())->method('unsetElement');
        $layout->expects(self::never())->method('createBlock');
        $layout->expects(self::never())->method('setChild');

        (new ApplyHyvaLayoutOverrides($isHyvaTheme))->execute($this->makeObserver($layout));
    }

    public function testSwapsCartBlockOnHyvaCartPage(): void
    {
        $layout = $this->createLayoutMock(['checkout_cart_index']);

        $lumaBlock = $this->createMock(AbstractBlock::class);
        $hyvaBlock = $this->createMock(AbstractBlock::class);
        $hyvaBlock->method('getNameInLayout')->willReturn(self::CART_HYVA_BLOCK);

        $layout->method('getBlock')->willReturnMap([
            [self::CART_LUMA_BLOCK, $lumaBlock],
            [self::CART_HYVA_BLOCK, false],
        ]);
        $layout->expects(self::once())->method('unsetElement')->with(self::CART_LUMA_BLOCK);
        $layout->expects(self::once())
            ->method('createBlock')
            ->with(
                self::CART_HYVA_CLASS,
                self::CART_HYVA_BLOCK,
                ['data' => ['template' => self::CART_HYVA_TEMPLATE]]
            )
            ->willReturn($hyvaBlock);
        $layout->method('hasElement')->with(self::CART_CONTAINER)->willReturn(true);
        $layout->expects(self::once())
            ->method('setChild')
            ->with(self::CART_CONTAINER, self::CART_HYVA_BLOCK, self::CART_HYVA_BLOCK);

        (new ApplyHyvaLayoutOverrides($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testSkipsCartSwapWhenHyvaBlockAlreadyExists(): void
    {
        $layout = $this->createLayoutMock(['checkout_cart_index']);

        $layout->method('getBlock')->willReturnMap([
            [self::CART_LUMA_BLOCK, $this->createMock(AbstractBlock::class)],
            [self::CART_HYVA_BLOCK, $this->createMock(AbstractBlock::class)],
        ]);
        $layout->expects(self::once())->method('unsetElement')->with(self::CART_LUMA_BLOCK);
        $layout->expects(self::never())->method('createBlock');
        $layout->expects(self::never())->method('setChild');

        (new ApplyHyvaLayoutOverrides($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testSkipsContentAttachmentWhenContainerMissing(): void
    {
        $layout = $this->createLayoutMock(['checkout_cart_index']);

        $hyvaBlock = $this->createMock(AbstractBlock::class);
        $hyvaBlock->method('getNameInLayout')->willReturn(self::CART_HYVA_BLOCK);

        $layout->method('getBlock')->willReturnMap([
            [self::CART_LUMA_BLOCK, false],
            [self::CART_HYVA_BLOCK, false],
        ]);
        $layout->method('createBlock')->willReturn($hyvaBlock);
        $layout->method('hasElement')->with(self::CART_CONTAINER)->willReturn(false);
        $layout->expects(self::never())->method('setChild');

        (new ApplyHyvaLayoutOverrides($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testRetargetsPdpBlockTemplateOnHyvaProductPage(): void
    {
        $layout = $this->createLayoutMock(['catalog_product_view']);

        $pdpBlock = $this->createMock(AbstractBlock::class);
        $pdpBlock->expects(self::once())->method('setTemplate')->with(self::PDP_HYVA_TEMPLATE);

        $layout->method('getBlock')->with(self::PDP_BLOCK)->willReturn($pdpBlock);

        (new ApplyHyvaLayoutOverrides($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testPdpOverrideSilentWhenBlockMissing(): void
    {
        $layout = $this->createLayoutMock(['catalog_product_view']);
        $layout->method('getBlock')->with(self::PDP_BLOCK)->willReturn(false);

        // Must not blow up and must not attempt setTemplate on false.
        $this->expectNotToPerformAssertions();

        (new ApplyHyvaLayoutOverrides($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testIgnoresUnrelatedLayoutHandles(): void
    {
        $layout = $this->createLayoutMock(['cms_page_view']);
        $layout->expects(self::never())->method('getBlock');
        $layout->expects(self::never())->method('unsetElement');
        $layout->expects(self::never())->method('createBlock');

        (new ApplyHyvaLayoutOverrides($this->hyvaTheme()))->execute($this->makeObserver($layout));
    }

    public function testSilentWhenObserverEventHasNoLayout(): void
    {
        $event = new DataObject();
        $observer = new Observer();
        $observer->setEvent($event);

        $this->expectNotToPerformAssertions();

        (new ApplyHyvaLayoutOverrides($this->hyvaTheme()))->execute($observer);
    }

    /**
     * @return LayoutInterface&MockObject
     */
    private function createLayoutMock(array $handles)
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('getHandles')->willReturn($handles);

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getUpdate')->willReturn($processor);

        return $layout;
    }

    /**
     * @param LayoutInterface&MockObject $layout
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
