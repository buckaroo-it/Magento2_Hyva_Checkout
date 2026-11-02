<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\ViewModel;

use Buckaroo\HyvaCheckout\ViewModel\ApplePayExpressConfig;
use Buckaroo\Magento2\Block\Catalog\Product\View\Applepay;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

/**
 * The express button config only carries what the Apple Pay sheet needs.
 */
class ApplePayExpressConfigTest extends TestCase
{
    public function testExposesOnlyTheSheetSettings(): void
    {
        $block = $this->createMock(Applepay::class);
        $block->method('getApplepayConfig')->willReturn(json_encode([
            'payment' => ['buckaroo' => ['buckaroo_magento2_applepay' => [
                'storeName'         => 'Store',
                'currency'          => 'EUR',
                'country'           => 'NL',
                'cultureCode'       => 'nl-NL',
                'guid'              => 'GUID',
                'buttonStyle'       => 'black',
                'supportedNetworks' => ['visa'],
                'availableButtons'  => ['Product'],
                'integrationMode'   => '0',
                'secretKey'         => 'should-not-leak',
            ]]],
        ]));

        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($this->createMock(Quote::class));

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://shop.example/');
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('FORMKEY');

        $config = (new ApplePayExpressConfig($this->createMock(Registry::class), $session, $url, $formKey))
            ->getConfig($block, 'Cart');

        self::assertSame(
            ['storeName', 'currency', 'country', 'cultureCode', 'guid', 'buttonStyle', 'supportedNetworks',
                'page', 'baseUrl', 'formKey', 'amount'],
            array_keys($config)
        );
        self::assertSame('cart', $config['page']);
        self::assertArrayNotHasKey('secretKey', $config);
    }
}
