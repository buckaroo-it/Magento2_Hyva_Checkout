<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Magewire\Payment\Method;

use Buckaroo\HyvaCheckout\Magewire\Payment\Method\ZakelijkOpRekening;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ZakelijkOpRekening as ConfigProvider;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\ErrorEventMessage;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\Success;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rakit\Validation\Validator;

/**
 * The Hyvä component collects the fields the Magento 2 checkout sends for
 * Zakelijk op rekening: the CoC number, and a telephone number when the
 * billing address has no valid one.
 */
class ZakelijkOpRekeningTest extends TestCase
{
    /** @var Payment|MockObject */
    private $payment;

    /** @var Address|MockObject */
    private $billingAddress;

    /** @var CartRepositoryInterface|MockObject */
    private $quoteRepository;

    /** @var ConfigProvider|MockObject */
    private $configProvider;

    private array $paymentInfo = [];

    protected function setUp(): void
    {
        $this->payment = $this->createMock(Payment::class);
        $this->payment->method('getAdditionalInformation')
            ->willReturnCallback(fn ($key) => $this->paymentInfo[$key] ?? null);
        $this->payment->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) {
                $this->paymentInfo[$key] = $value;
                return $this->payment;
            });

        $this->billingAddress = $this->createMock(Address::class);
        $this->billingAddress->method('getCountryId')->willReturn('NL');
        $this->billingAddress->method('getFirstname')->willReturn('Jan');
        $this->billingAddress->method('getLastname')->willReturn('Jansen');

        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
    }

    public function testCompletionNeedsTheCocNumber(): void
    {
        $this->billingAddress->method('getTelephone')->willReturn('0612345678');
        $component = $this->createComponent();

        self::assertInstanceOf(ErrorEventMessage::class, $component->evaluateCompletion($this->resultFactory()));

        $component->coc = '12345678';
        self::assertInstanceOf(Success::class, $component->evaluateCompletion($this->resultFactory()));
    }

    public function testTelephoneIsAskedOnlyWhenTheBillingNumberIsInvalid(): void
    {
        $this->billingAddress->method('getTelephone')->willReturn('123');
        $component = $this->createComponent();
        $component->coc = '12345678';

        self::assertTrue($component->showPhone());
        self::assertInstanceOf(ErrorEventMessage::class, $component->evaluateCompletion($this->resultFactory()));

        $component->phone = '0612345678';
        self::assertInstanceOf(Success::class, $component->evaluateCompletion($this->resultFactory()));
    }

    public function testValidBillingNumberHidesTheTelephoneField(): void
    {
        $this->billingAddress->method('getTelephone')->willReturn('0612345678');

        self::assertFalse($this->createComponent()->showPhone());
    }

    public function testFieldsAreStoredUnderTheKeysTheGatewayReads(): void
    {
        $this->billingAddress->method('getTelephone')->willReturn('123');
        $this->quoteRepository->expects(self::exactly(2))->method('save');
        $component = $this->createComponent();

        $component->updatedCoc(' 12345678 ');
        $component->updatedPhone('0612345678');

        self::assertSame('12345678', $this->paymentInfo['customer_chamberOfCommerce']);
        self::assertSame('0612345678', $this->paymentInfo['customer_telephone']);
    }

    public function testMountRestoresStoredValues(): void
    {
        $this->paymentInfo = ['customer_chamberOfCommerce' => '87654321', 'customer_telephone' => '0698765432'];
        $component = $this->createComponent();

        $component->mount();

        self::assertSame('87654321', $component->coc);
        self::assertSame('0698765432', $component->phone);
        self::assertSame('Jan Jansen', $component->fullName);
    }

    public function testTooltipComesFromTheMethodConfig(): void
    {
        $this->configProvider->method('getConfig')->willReturn([
            'payment' => ['buckaroo' => [ConfigProvider::CODE => [
                'tooltipText' => 'Voor iedereen, powered by ABN AMRO,',
                'tooltipUrl' => 'https://example.test/faq/',
            ]]],
        ]);
        $component = $this->createComponent();

        self::assertSame('Voor iedereen, powered by ABN AMRO,', $component->getTooltipText());
        self::assertSame('https://example.test/faq/', $component->getTooltipUrl());
    }

    public function testTooltipUrlFallsBackToTheMethodDefault(): void
    {
        $this->configProvider->method('getConfig')->willReturn([]);

        self::assertSame(ConfigProvider::TOOLTIP_URL, $this->createComponent()->getTooltipUrl());
    }

    private function createComponent(): ZakelijkOpRekening
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($this->payment);
        $quote->method('getBillingAddress')->willReturn($this->billingAddress);
        $session = $this->createMock(SessionCheckout::class);
        $session->method('getQuote')->willReturn($quote);

        return new ZakelijkOpRekening(new Validator(), $session, $this->quoteRepository, $this->configProvider);
    }

    private function resultFactory(): EvaluationResultFactory
    {
        $error = $this->createMock(ErrorEventMessage::class);
        $error->method('withCustomEvent')->willReturnSelf();
        $error->method('withMessage')->willReturnSelf();
        $factory = $this->createMock(EvaluationResultFactory::class);
        $factory->method('createErrorMessageEvent')->willReturn($error);
        $factory->method('createSuccess')->willReturn($this->createMock(Success::class));

        return $factory;
    }
}
