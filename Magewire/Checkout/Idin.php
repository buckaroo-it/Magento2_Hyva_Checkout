<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Checkout;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Buckaroo\Transaction\Response\TransactionResponse;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;

/**
 * iDIN age verification step, the Hyvä counterpart of the Magento 2 checkout iDIN step.
 *
 * Shown when the cart needs an age verification that is not done yet; the order cannot be
 * placed until the shopper is verified. Verification itself runs on the Buckaroo side and
 * returns through the Magento 2 plugin (buckaroo/redirect/idinProcess).
 *
 * Keep this class name: the Magento 2 plugin (Buckaroo\Magento2\Model\Idin\CheckoutSupport) looks
 * for it to tell whether the Hyvä Checkout of a store has an iDIN step.
 */
class Idin extends Component implements EvaluationInterface
{
    public ?string $issuer = null;

    protected array $uncallables = ['isRequired', 'getIssuers'];

    protected $loader = ['verify' => 'Redirecting to your bank'];

    private SessionCheckout $sessionCheckout;

    private IdinConfigProvider $idinConfigProvider;

    private BuilderInterface $requestDataBuilder;

    private TransferFactoryInterface $transferFactory;

    private ClientInterface $clientInterface;

    private BuckarooLoggerInterface $logger;

    private ?bool $required = null;

    public function __construct(
        SessionCheckout $sessionCheckout,
        IdinConfigProvider $idinConfigProvider,
        BuilderInterface $requestDataBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $clientInterface,
        BuckarooLoggerInterface $logger
    ) {
        $this->sessionCheckout = $sessionCheckout;
        $this->idinConfigProvider = $idinConfigProvider;
        $this->requestDataBuilder = $requestDataBuilder;
        $this->transferFactory = $transferFactory;
        $this->clientInterface = $clientInterface;
        $this->logger = $logger;
    }

    /**
     * Whether the cart still needs iDIN age verification
     */
    public function isRequired(): bool
    {
        if ($this->required !== null) {
            return $this->required;
        }

        try {
            /** @var Quote $quote */
            $quote = $this->sessionCheckout->getQuote();

            // The same check the Magento 2 plugin runs when the order is placed
            $status = $this->idinConfigProvider->getQuoteIdinStatus($quote);

            $this->required = !empty($status['active']);
        } catch (\Throwable $exception) {
            $this->logger->addError(sprintf(
                '[iDIN] | [Hyvä] | [%s:%s] - Could not determine iDIN status | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $exception->getMessage()
            ));
            $this->required = true;
        }

        return $this->required;
    }

    /**
     * Banks the shopper can verify with
     *
     * @return array<int, array{name: string, code: string}>
     */
    public function getIssuers(): array
    {
        try {
            return $this->idinConfigProvider->getIssuers();
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * Start the verification and send the shopper to their bank
     */
    public function verify(): void
    {
        $issuer = (string)$this->issuer;
        $codes = array_column($this->getIssuers(), 'code');

        if ($issuer === '' || !in_array($issuer, $codes, true)) {
            $this->dispatchErrorMessage(__('Select your bank'));
            return;
        }

        try {
            $transfer = $this->transferFactory->create(
                $this->requestDataBuilder->build(['issuer' => $issuer])
            );
            $response = $this->clientInterface->placeRequest($transfer);
            $transaction = $response['object'] ?? null;

            if ($transaction instanceof TransactionResponse
                && ($transaction->isSuccess() || $transaction->isPendingProcessing())
            ) {
                $redirectUrl = $transaction->toArray()['RequiredAction']['RedirectURL'] ?? null;
                if (is_string($redirectUrl) && $redirectUrl !== '') {
                    $this->redirect($redirectUrl);
                    return;
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->addError(sprintf(
                '[iDIN] | [Hyvä] | [%s:%s] - Could not start iDIN verification | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $exception->getMessage()
            ));
        }

        $this->dispatchErrorMessage(__('Unfortunately iDIN not verified!'));
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->isRequired()) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Please verify your age with iDIN before placing this order.');
        }

        return $resultFactory->createSuccess();
    }
}
