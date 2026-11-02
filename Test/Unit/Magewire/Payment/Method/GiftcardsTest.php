<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Magewire\Payment\Method;

use Buckaroo\HyvaCheckout\Magewire\Payment\Method\Giftcards;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as MethodConfigProvider;
use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface as GiftcardRequest;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Buckaroo\Magento2\Service\Giftcard\AttemptLimit;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Applying an inline giftcard goes through the shared failed-attempt limit and
 * is refused when the store sends giftcards to the hosted payment page.
 */
class GiftcardsTest extends TestCase
{
    /** @var Quote|MockObject */
    private $quote;

    /** @var GiftcardRequest|MockObject */
    private $giftcardRequest;

    /** @var GiftcardResponse|MockObject */
    private $giftcardResponse;

    /** @var AttemptLimit|MockObject */
    private $attemptLimit;

    /** @var MethodConfigProvider|MockObject */
    private $configProvider;

    /** @var Log|MockObject */
    private $logger;

    protected function setUp(): void
    {
        $this->quote = $this->createMock(Quote::class);
        $this->giftcardRequest = $this->createMock(GiftcardRequest::class);
        foreach (['setCardId', 'setCardNumber', 'setPin', 'setQuote'] as $setter) {
            $this->giftcardRequest->method($setter)->willReturnSelf();
        }
        $this->giftcardResponse = $this->createMock(GiftcardResponse::class);
        $this->attemptLimit = $this->createMock(AttemptLimit::class);
        $this->configProvider = $this->createMock(MethodConfigProvider::class);
        $this->logger = $this->createMock(Log::class);
    }

    public function testRefusesWhenGiftcardsAreNotInline(): void
    {
        $this->configureInline(false);

        $this->attemptLimit->expects(self::never())->method('assertWithinLimit');
        $this->giftcardRequest->expects(self::never())->method('send');

        $component = $this->createComponent();
        $component->applyGiftcard('fashioncheque', '123', '456');

        self::assertArrayHasKey('error', $this->lastEmittedParams($component));
    }

    public function testStopsBeforeTheGatewayWhenTheLimitIsReached(): void
    {
        $this->configureInline(true);

        $this->attemptLimit->method('assertWithinLimit')
            ->willThrowException(new ApiException('Too many giftcard attempts. Please try again later.'));
        $this->giftcardRequest->expects(self::never())->method('send');

        $component = $this->createComponent();
        $component->applyGiftcard('fashioncheque', '123', '456');

        self::assertArrayHasKey('error', $this->lastEmittedParams($component));
    }

    public function testCountsAGatewayFailure(): void
    {
        $this->configureInline(true);

        $this->giftcardRequest->method('send')->willThrowException(new ApiException('declined'));
        $this->attemptLimit->expects(self::once())->method('registerFailedAttempt')->with($this->quote);

        $this->createComponent()->applyGiftcard('fashioncheque', '123', '456');
    }

    public function testCountsARejectedCard(): void
    {
        $this->configureInline(true);

        $this->giftcardRequest->method('send')->willReturn($this->createMock(TransactionResponse::class));
        $this->giftcardResponse->method('getErrorMessage')->willReturn('Invalid card');
        $this->attemptLimit->expects(self::once())->method('registerFailedAttempt')->with($this->quote);

        $this->createComponent()->applyGiftcard('fashioncheque', '123', '456');
    }

    public function testDoesNotCountAnAcceptedCard(): void
    {
        $this->configureInline(true);

        $this->giftcardRequest->method('send')->willReturn($this->createMock(TransactionResponse::class));
        $this->giftcardResponse->method('getErrorMessage')->willReturn(null);
        $this->giftcardResponse->method('getRemainderAmount')->willReturn(0.0);
        $this->attemptLimit->expects(self::never())->method('registerFailedAttempt');

        $component = $this->createComponent();
        $component->applyGiftcard('fashioncheque', '123', '456');

        self::assertTrue($component->canSubmit);
    }

    public function testDoesNotLogCardDetails(): void
    {
        $this->configureInline(true);

        $this->giftcardRequest->method('send')->willReturn($this->createMock(TransactionResponse::class));
        $this->giftcardResponse->method('getErrorMessage')->willReturn('Invalid card');

        $logged = [];
        $collect = function ($message) use (&$logged) {
            $logged[] = (string)$message;
            return true;
        };
        $this->logger->method('addDebug')->willReturnCallback($collect);
        $this->logger->method('addError')->willReturnCallback($collect);

        $this->createComponent()->applyGiftcard('fashioncheque', '6064365011234567', '9876');

        self::assertIsArray($logged);
        foreach ($logged as $line) {
            self::assertStringNotContainsString('6064365011234567', $line);
            self::assertStringNotContainsString('9876', $line);
        }
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

        return new Giftcards(
            $this->createMock(UrlInterface::class),
            $session,
            $this->createMock(PaymentGroupTransaction::class),
            $this->configProvider,
            $this->giftcardRequest,
            $this->giftcardResponse,
            $this->logger,
            $pricing,
            $this->attemptLimit
        );
    }

    private function lastEmittedParams(Giftcards $component): array
    {
        $events = $component->getEventQueue();
        self::assertNotEmpty($events);

        $params = end($events)->getParams();

        return $params[0] ?? [];
    }
}
