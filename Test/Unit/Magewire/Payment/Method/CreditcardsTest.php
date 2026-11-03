<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Magewire\Payment\Method;

use Buckaroo\HyvaCheckout\Magewire\Payment\Method\Creditcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards as MethodConfigProvider;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Quote\Api\CartRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Rakit\Validation\Validator;

/**
 * Hosted Fields use the placeholders and styling configured for the payment method.
 */
class CreditcardsTest extends TestCase
{
    public function testHostedFieldsSettingsComeFromThePaymentMethodConfiguration(): void
    {
        $config = $this->createMock(MethodConfigProvider::class);
        $config->method('getPlaceholderCardholderName')->willReturn('Name on card');
        $config->method('getPlaceholderCardNumber')->willReturn('Card number');
        $config->method('getPlaceholderExpiryDate')->willReturn('MM/YY');
        $config->method('getPlaceholderCvc')->willReturn('CVC');
        $config->method('getFieldTextColor')->willReturn('#111111');
        $config->method('getFieldBackgroundColor')->willReturn('#ffffff');
        $config->method('getFieldBorderColor')->willReturn('');
        $config->method('getFieldPlaceholderColor')->willReturn('#999999');
        $config->method('getFieldFontSize')->willReturn('16px');
        $config->method('getFieldFontFamily')->willReturn('Arial');
        $config->method('getFieldBorderRadius')->willReturn('4px');

        $component = new Creditcards(
            $this->createMock(Validator::class),
            $this->createMock(SessionCheckout::class),
            $this->createMock(CartRepositoryInterface::class),
            $config
        );

        $this->assertSame([
            'placeholders' => [
                'cardholderName' => 'Name on card',
                'cardNumber'     => 'Card number',
                'expiryDate'     => 'MM/YY',
                'cvc'            => 'CVC',
            ],
            'styling' => [
                'textColor'        => '#111111',
                'backgroundColor'  => '#ffffff',
                'borderColor'      => '',
                'placeholderColor' => '#999999',
                'fontSize'         => '16px',
                'fontFamily'       => 'Arial',
                'borderRadius'     => '4px',
            ],
        ], $component->getHostedFieldsSettings());
    }
}
