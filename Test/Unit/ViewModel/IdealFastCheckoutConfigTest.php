<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\ViewModel;

use Buckaroo\HyvaCheckout\ViewModel\IdealFastCheckoutConfig;
use Buckaroo\Magento2\Block\Catalog\Product\View\IdealFastCheckout;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The Hyvä iDEAL Fast Checkout button follows the Magento 2 plugin settings and the iDIN check.
 */
class IdealFastCheckoutConfigTest extends TestCase
{
    /** @var IdealFastCheckout|MockObject */
    private $block;

    /** @var Quote|MockObject */
    private $quote;

    protected function setUp(): void
    {
        $this->block = $this->createMock(IdealFastCheckout::class);
        $this->block->method('getButtonVariant')->willReturn('Magenta');
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItemsCount'])
            ->getMock();
    }

    private function getViewModel(): IdealFastCheckoutConfig
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($this->quote);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getBaseUrl')->willReturn('https://shop.example/');
        $url->method('getUrl')->willReturn('https://shop.example/checkout/onepage/success/');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getCode')->willReturn('nl_store');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new IdealFastCheckoutConfig($session, $url, $storeManager);
    }

    public function testShownOnProductPageWhenEnabled(): void
    {
        $this->block->method('canShowButton')->with('Product')->willReturn(true);
        $this->block->method('isIdinVerificationRequired')->willReturn(false);

        $this->assertTrue($this->getViewModel()->canShow($this->block, 'Product'));
    }

    public function testHiddenWhenDisabledForThePage(): void
    {
        $this->block->method('canShowButton')->willReturn(false);
        $this->block->method('isIdinVerificationRequired')->willReturn(false);

        $this->assertFalse($this->getViewModel()->canShow($this->block, 'Product'));
    }

    public function testHiddenWhileIdinVerificationIsRequired(): void
    {
        $this->block->method('canShowButton')->willReturn(true);
        $this->block->method('isIdinVerificationRequired')->willReturn(true);

        $this->assertFalse($this->getViewModel()->canShow($this->block, 'Cart'));
    }

    public function testHiddenOnEmptyCart(): void
    {
        $this->block->method('canShowButton')->willReturn(true);
        $this->block->method('isIdinVerificationRequired')->willReturn(false);
        $this->quote->method('getItemsCount')->willReturn(0);

        $this->assertFalse($this->getViewModel()->canShow($this->block, 'Cart'));
    }

    public function testConfigUsesTheStoreCodeInTheRestUrl(): void
    {
        $this->assertSame([
            'page'          => 'cart',
            'buttonVariant' => 'Magenta',
            'restUrl'       => 'https://shop.example/rest/nl_store/V1/',
            'successUrl'    => 'https://shop.example/checkout/onepage/success/',
        ], $this->getViewModel()->getConfig($this->block, 'Cart'));
    }
}
