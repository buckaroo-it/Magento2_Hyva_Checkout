<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Magewire\Payment\Method;

use Buckaroo\HyvaCheckout\Magewire\Payment\Method\Giftcards;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as MethodConfigProvider;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\PartialPayment\ApplyResult;
use Buckaroo\Magento2\Model\PartialPayment\ApplyService;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\Giftcard as GiftcardInstrument;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\GiftcardFactory;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentMethods;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GiftcardsTest extends TestCase
{
    /** @var Quote|MockObject */
    private $quote;

    /** @var ApplyService|MockObject */
    private $applyService;

    /** @var PaymentGroupTransaction|MockObject */
    private $groupTransaction;

    /** @var PartialPaymentMethods|MockObject */
    private $partialPaymentMethods;

    /** @var MethodConfigProvider|MockObject */
    private $configProvider;

    /** @var Log|MockObject */
    private $logger;

    protected function setUp(): void
    {
        $this->quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $this->quote->setReservedOrderId('000000042');
        $this->quote->setGrandTotal(34.0);
        $this->applyService = $this->createMock(ApplyService::class);
        $this->groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $this->partialPaymentMethods = $this->createMock(PartialPaymentMethods::class);
        $this->partialPaymentMethods->method('find')->willReturnCallback(function ($code) {
            return $code === 'buckaroovoucher'
                ? ['code' => $code, 'label' => 'Voucher', 'logo' => null, 'type' => PartialPaymentMethods::TYPE_VOUCHER]
                : ['code' => $code, 'label' => $code, 'logo' => null, 'type' => PartialPaymentMethods::TYPE_GIFTCARD];
        });
        $this->configProvider = $this->createMock(MethodConfigProvider::class);
        $this->logger = $this->createMock(Log::class);
    }

    public function testRefusesWhenGiftcardsAreNotInline(): void
    {
        $this->configureInline(false);
        $this->applyService->expects(self::never())->method('apply');

        $component = $this->createComponent();
        $component->applyGiftcard('fashioncheque', '123', '456');

        self::assertArrayHasKey('error', $this->lastEmittedParams($component));
    }

    public function testShowsTheReasonOfARefusedCard(): void
    {
        $this->configureInline(true);
        $this->applyService->method('apply')
            ->willThrowException(new PartialPaymentRejectedException(__('Card does not exist')));

        $component = $this->createComponent();
        $component->applyGiftcard('fashioncheque', '123', '456');

        self::assertSame('Card does not exist', (string)$this->lastEmittedParams($component)['error']);
        self::assertFalse($component->canSubmit);
    }

    public function testAnAcceptedCardThatCoversTheOrderAllowsPlacingIt(): void
    {
        $this->configureInline(true);
        $this->applyService->method('apply')->willReturn($this->createResult(0.0));
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(34.0);
        $this->groupTransaction->method('getGroupTransactionItems')
            ->willReturn([new DataObject(['servicecode' => 'fashioncheque'])]);

        $component = $this->createComponent();
        $component->applyGiftcard('fashioncheque', '123', '456');

        self::assertTrue($component->canSubmit);
        self::assertSame(0.0, $this->lastEmittedParams($component)['remainder_amount']);
    }

    public function testAnOrderPaidByVouchersOnlyIsNoGiftcardPayment(): void
    {
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(34.0);
        $this->groupTransaction->method('getGroupTransactionItems')
            ->willReturn([new DataObject(['servicecode' => 'buckaroovoucher'])]);

        $component = $this->createComponent();
        $component->mount();

        self::assertFalse($component->canSubmit);
    }

    /**
     * The 'cards cover the order' state is recomputed when the total can change (shipping, coupon)
     */
    public function testCoverageIsRecomputedWhenTheTotalGrows(): void
    {
        $this->groupTransaction->method('getAlreadyPaid')->willReturn(34.0);
        $this->groupTransaction->method('getGroupTransactionItems')
            ->willReturn([new DataObject(['servicecode' => 'fashioncheque'])]);

        $component = $this->createComponent();
        $component->mount();
        self::assertTrue($component->canSubmit);

        $this->quote->setGrandTotal(39.0);
        $component->refreshCoverage();

        self::assertFalse($component->canSubmit);
    }

    public function testDoesNotLogCardDetails(): void
    {
        $this->configureInline(true);
        $this->applyService->method('apply')->willThrowException(new \RuntimeException('db down'));

        $logged = [];
        $collect = function ($message) use (&$logged) {
            $logged[] = (string)$message;
            return true;
        };
        $this->logger->method('addDebug')->willReturnCallback($collect);
        $this->logger->method('addError')->willReturnCallback($collect);

        $this->createComponent()->applyGiftcard('fashioncheque', '6064365011234567', '9876');

        self::assertNotEmpty($logged);
        foreach ($logged as $line) {
            self::assertStringNotContainsString('6064365011234567', $line);
            self::assertStringNotContainsString('9876', $line);
        }
    }

    private function createResult(float $remainder): ApplyResult
    {
        return new ApplyResult(10.0, $remainder, 34.0, 'EUR', $this->createMock(GroupTransaction::class));
    }

    private function configureInline(bool $inline): void
    {
        $this->configProvider->method('getConfig')->willReturn([
            'payment' => [
                'buckaroo' => [
                    'buckaroo_magento2_giftcards' => [
                        'groupGiftcards'     => !$inline,
                        'availableGiftcards' => [['code' => 'fashioncheque', 'title' => 'Fashioncheque']],
                    ],
                ],
            ],
        ]);
    }

    private function createComponent(): Giftcards
    {
        $session = $this->createMock(SessionCheckout::class);
        $session->method('getQuote')->willReturn($this->quote);

        $pricing = $this->createMock(PricingHelper::class);
        $pricing->method('currency')->willReturn('€0.00');

        $giftcardFactory = $this->createMock(GiftcardFactory::class);
        $giftcardFactory->method('create')->willReturn($this->createMock(GiftcardInstrument::class));

        return new Giftcards(
            $this->createMock(UrlInterface::class),
            $session,
            $this->groupTransaction,
            $this->configProvider,
            $this->logger,
            $pricing,
            $this->applyService,
            $giftcardFactory,
            $this->partialPaymentMethods
        );
    }

    private function lastEmittedParams(Giftcards $component): array
    {
        $events = array_values(array_filter($component->getBrowserEvents(), function ($event) {
            return $event['event'] === 'buckaroo-giftcard-response';
        }));
        self::assertNotEmpty($events);

        return end($events)['data'];
    }
}
