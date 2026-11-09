<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Magewire\Payment\Method;

use Buckaroo\HyvaCheckout\Magewire\Payment\Method\Applepay;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\ErrorEventMessage;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\Success;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Quote\Model\Quote\Address\Total;
use PHPUnit\Framework\TestCase;

/**
 * The Apple Pay card network is stored on the quote payment so the main plugin's
 * card network check sees it at order placement.
 */
class ApplepayTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function networkProvider(): array
    {
        return [
            'visa'          => ['Visa', 'Visa'],
            'mastercard'    => ['MasterCard', 'MasterCard'],
            'trimmed'       => [' Amex ', 'Amex'],
            'not provided'  => ['', ''],
            'markup'        => ['<b>Visa</b>', ''],
            'too long'      => [str_repeat('a', 40), ''],
        ];
    }

    /**
     * @dataProvider networkProvider
     */
    public function testStoresTheCardNetwork(string $network, string $expected): void
    {
        $stored = [];
        $payment = $this->createMock(Payment::class);
        $payment->method('setAdditionalInformation')->willReturnCallback(
            function ($key, $value) use (&$stored, $payment) {
                $stored[$key] = $value;
                return $payment;
            }
        );

        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);

        $session = $this->createMock(SessionCheckout::class);
        $session->method('getQuote')->willReturn($quote);

        $reflection = new \ReflectionClass(Applepay::class);
        /** @var Applepay $component */
        $component = $reflection->newInstanceWithoutConstructor();
        foreach (['sessionCheckout' => $session, 'quoteRepository' => $this->createMock(CartRepositoryInterface::class)]
                 as $property => $value) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($component, $value);
        }

        $component->updateData('{"paymentData":{}}', '{}', $network);

        self::assertSame($expected, $stored['applepayCardNetwork']);
        self::assertSame('{"paymentData":{}}', $stored['applepayTransaction']);
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function completionProvider(): array
    {
        return [
            'inline mode without Apple Pay data' => ['0', null, ErrorEventMessage::class],
            'inline mode with Apple Pay data'    => ['0', '{"paymentData":{}}', Success::class],
            'redirect mode without data'         => ['1', null, Success::class],
        ];
    }

    /**
     * @dataProvider completionProvider
     */
    public function testOrderNeedsApplePayDataInInlineMode(string $mode, ?string $stored, string $expected): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnMap([['applepayTransaction', $stored]]);
        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);
        $session = $this->createMock(SessionCheckout::class);
        $session->method('getQuote')->willReturn($quote);

        $reflection = new \ReflectionClass(Applepay::class);
        /** @var Applepay $component */
        $component = $reflection->newInstanceWithoutConstructor();
        $prop = $reflection->getProperty('sessionCheckout');
        $prop->setAccessible(true);
        $prop->setValue($component, $session);
        $component->config = ['integrationMode' => $mode];

        $error = $this->createMock(ErrorEventMessage::class);
        $error->method('withCustomEvent')->willReturnSelf();
        $error->method('withMessage')->willReturnSelf();
        $factory = $this->createMock(EvaluationResultFactory::class);
        $factory->method('createErrorMessageEvent')->willReturn($error);
        $factory->method('createSuccess')->willReturn($this->createMock(Success::class));

        self::assertInstanceOf($expected, $component->evaluateCompletion($factory));
    }

    public function testTotalsAreUnchangedAfterAJsonRoundTrip(): void
    {
        $totals = [];
        foreach (['shipping' => 5.0, 'tax' => 6.5] as $code => $value) {
            $total = $this->createMock(Total::class);
            $total->method('getData')->willReturnMap([['value', null, $value], ['title', null, $code]]);
            $totals[$code] = $total;
        }
        $totals['grand_total'] = $this->createMock(Total::class);
        $totals['grand_total']->method('getData')->willReturnMap([['value', null, 40.0], ['title', null, 'Total']]);

        $quote = $this->createMock(Quote::class);
        $quote->method('getTotals')->willReturn($totals);
        $session = $this->createMock(SessionCheckout::class);
        $session->method('getQuote')->willReturn($quote);

        $reflection = new \ReflectionClass(Applepay::class);
        $component = $reflection->newInstanceWithoutConstructor();
        $prop = $reflection->getProperty('sessionCheckout');
        $prop->setAccessible(true);
        $prop->setValue($component, $session);

        foreach (['getTotalLines', 'getGrandTotal'] as $method) {
            $data = $reflection->getMethod($method)->invoke($component);
            self::assertSame(serialize($data), serialize(json_decode(json_encode($data), true)), $method);
        }
        self::assertSame('40.00', $reflection->getMethod('getGrandTotal')->invoke($component)['amount']);
    }
}
