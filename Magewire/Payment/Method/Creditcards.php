<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards as MethodConfigProvider;


class Creditcards extends Component\Form implements EvaluationInterface
{
    public ?string $encriptedData = null;

    public array $issuerImages  = [];

    protected array $uncallables = ['getHostedFieldsSettings'];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected MethodConfigProvider $methodConfigProvider;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        MethodConfigProvider $methodConfigProvider
    ) {
        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->methodConfigProvider = $methodConfigProvider;
    }

    public function mount()
    {
        $this->issuerImages = $this->getIssuerImages();
    }

    public function updatedEncryptedData(string $value, ?string $issuer): ?string
    {
        $value = empty($value) ? null : $value;
        try {
            $this->encriptedData = $value;
            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('customer_encrypteddata', $value);
            $quote->getPayment()->setAdditionalInformation(
                'customer_creditcardcompany',
                $issuer ?? 'visa'
            );

            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return $value;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->encriptedData === null) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('Please fill all required payment fields');
        }

        return $resultFactory->createSuccess();
    }

    /**
     * Hosted Fields placeholders and styling from the Magento 2 plugin configuration
     * (Stores > Configuration > Payment Methods > Buckaroo > Credit and debit cards),
     * the same settings the Magento 2 checkout uses.
     *
     * @return array{placeholders: array<string, string>, styling: array<string, string>}
     */
    public function getHostedFieldsSettings(): array
    {
        $config = $this->methodConfigProvider;

        return [
            'placeholders' => [
                'cardholderName' => $config->getPlaceholderCardholderName(),
                'cardNumber'     => $config->getPlaceholderCardNumber(),
                'expiryDate'     => $config->getPlaceholderExpiryDate(),
                'cvc'            => $config->getPlaceholderCvc(),
            ],
            'styling' => [
                'textColor'        => $config->getFieldTextColor(),
                'backgroundColor'  => $config->getFieldBackgroundColor(),
                'borderColor'      => $config->getFieldBorderColor(),
                'placeholderColor' => $config->getFieldPlaceholderColor(),
                'fontSize'         => $config->getFieldFontSize(),
                'fontFamily'       => $config->getFieldFontFamily(),
                'borderRadius'     => $config->getFieldBorderRadius(),
            ],
        ];
    }

    private function getIssuerImages(): array
    {
        $issuerImages = [];

        foreach($this->methodConfigProvider->formatIssuers() as $issuer) {
            if(isset($issuer['code']) && isset($issuer['img'])) {
                $issuerImages[$issuer['code']] = $issuer['img'];
            }
        }
        return $issuerImages;
    }
}
