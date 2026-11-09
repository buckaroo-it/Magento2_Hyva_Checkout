<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Unit\Magewire\Checkout;

use Buckaroo\HyvaCheckout\Magewire\Checkout\Idin;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Buckaroo\Transaction\Response\TransactionResponse;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\ErrorEventMessage;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\Success;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The Hyvä iDIN step blocks the order until the shopper is verified and starts the
 * verification only for one of the offered banks.
 */
class IdinTest extends TestCase
{
    /** @var IdinConfigProvider|MockObject */
    private $configProvider;

    /** @var BuilderInterface|MockObject */
    private $builder;

    /** @var ClientInterface|MockObject */
    private $client;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(IdinConfigProvider::class);
        $this->configProvider->method('getIssuers')->willReturn([
            ['name' => 'ING', 'code' => 'INGBNL2A'],
        ]);
        $this->builder = $this->createMock(BuilderInterface::class);
        $this->client = $this->createMock(ClientInterface::class);
    }

    private function getComponent(): Idin
    {
        $session = $this->createMock(SessionCheckout::class);
        $session->method('getQuote')->willReturn($this->createMock(Quote::class));

        $transferFactory = $this->createMock(TransferFactoryInterface::class);
        $transferFactory->method('create')->willReturn($this->createMock(TransferInterface::class));

        return new Idin(
            $session,
            $this->configProvider,
            $this->builder,
            $transferFactory,
            $this->client,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    private function getResultFactory(): EvaluationResultFactory
    {
        $factory = $this->createMock(EvaluationResultFactory::class);
        $error = $this->createMock(ErrorEventMessage::class);
        $error->method('withCustomEvent')->willReturnSelf();
        $error->method('withMessage')->willReturnSelf();
        $factory->method('createErrorMessageEvent')->willReturn($error);
        $factory->method('createSuccess')->willReturn($this->createMock(Success::class));

        return $factory;
    }

    public function testOrderIsBlockedWhileVerificationIsRequired(): void
    {
        $this->configProvider->method('getQuoteIdinStatus')->willReturn(['active' => true, 'verified' => false]);

        $result = $this->getComponent()->evaluateCompletion($this->getResultFactory());

        $this->assertInstanceOf(ErrorEventMessage::class, $result);
    }

    public function testOrderContinuesWhenNoVerificationIsRequired(): void
    {
        $this->configProvider->method('getQuoteIdinStatus')->willReturn(['active' => false, 'verified' => true]);

        $result = $this->getComponent()->evaluateCompletion($this->getResultFactory());

        $this->assertInstanceOf(Success::class, $result);
    }

    public function testOrderIsBlockedWhenStatusCannotBeRead(): void
    {
        $this->configProvider->method('getQuoteIdinStatus')->willThrowException(new \RuntimeException('no product'));

        $this->assertTrue($this->getComponent()->isRequired());
    }

    public function testUnknownBankIsNotSentToBuckaroo(): void
    {
        $this->builder->expects($this->never())->method('build');
        $this->client->expects($this->never())->method('placeRequest');

        $component = $this->getComponent();
        $component->issuer = 'EVILNL2A';
        $component->verify();

        $this->assertNull($component->getRedirect());
    }

    public function testShopperIsSentToTheBank(): void
    {
        $this->builder->expects($this->once())->method('build')
            ->with(['issuer' => 'INGBNL2A'])
            ->willReturn([]);

        $transaction = $this->createMock(TransactionResponse::class);
        $transaction->method('isSuccess')->willReturn(false);
        $transaction->method('isPendingProcessing')->willReturn(true);
        $transaction->method('toArray')->willReturn([
            'RequiredAction' => ['RedirectURL' => 'https://testcheckout.buckaroo.nl/html/redirect.ashx?r=1'],
        ]);
        $this->client->method('placeRequest')->willReturn(['object' => $transaction]);

        $component = $this->getComponent();
        $component->issuer = 'INGBNL2A';
        $component->verify();

        $this->assertSame(
            'https://testcheckout.buckaroo.nl/html/redirect.ashx?r=1',
            $component->getRedirect()->getUrl()
        );
    }

    public function testNoRedirectWhenBuckarooRefuses(): void
    {
        $this->builder->method('build')->willReturn([]);
        $transaction = $this->createMock(TransactionResponse::class);
        $transaction->method('isSuccess')->willReturn(false);
        $transaction->method('isPendingProcessing')->willReturn(false);
        $this->client->method('placeRequest')->willReturn(['object' => $transaction]);

        $component = $this->getComponent();
        $component->issuer = 'INGBNL2A';
        $component->verify();

        $this->assertNull($component->getRedirect());
    }
}
