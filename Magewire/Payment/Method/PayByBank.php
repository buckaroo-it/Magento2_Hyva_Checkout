<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Framework\View\Asset\Repository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayByBank as MethodPayByBank;

class PayByBank extends Component\Form implements EvaluationInterface
{
    public ?string $issuer = null;

    protected $loader = [
        'issuer' => 'Saving Bank issuer'
    ];

    protected $rules = [
        'issuer' => 'required'
    ];

    protected $messages = [
        'issuer:required' => 'The bank issuer is required'
    ];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected Repository $assetRepo;

    protected ScopeConfigInterface $scopeConfig;


    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,

    ) {
        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->assetRepo = $assetRepo;
        $this->scopeConfig = $scopeConfig;

    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $this->issuer = $this->getLastIssuer();
    }

    /**
     * Listen for bank issuer been updated.
     */
    public function updatedIssuer(string $value): ?string
    {
        $this->validateOnly();
        $value = empty($value) ? null : $value;

        try {
            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('issuer', $value);

            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return $value;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->issuer === null) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('The bank issuer is required');
        }

        return $resultFactory->createSuccess();
    }

    public function getIssuers(): array
    {
        return [
            [
                'name' => 'ABN AMRO',
                'code' => 'ABNANL2A',
                'imgName' => 'abnamro'
            ],
            [
                'name' => 'ASN Bank',
                'code' => 'ASNBNL21',
                'imgName' => 'asnbank'
            ],
            [
                'name' => 'ING',
                'code' => 'INGBNL2A',
                'imgName' => 'ing'
            ],
            [
                'name' => 'Knab Bank',
                'code' => 'KNABNL2H',
                'imgName' => 'knab'
            ],
            [
                'name' => 'Rabobank',
                'code' => 'RABONL2U',
                'imgName' => 'rabobank'
            ],
            [
                'name' => 'RegioBank',
                'code' => 'RBRBNL21',
                'imgName' => 'regiobank'
            ],
            [
                'name' => 'SNS Bank',
                'code' => 'SNSBNL2A',
                'imgName' => 'sns'
            ],
            [
                'name' => 'N26',
                'code' => 'NTSBDEB1',
                'imgName' => 'n26'
            ]
        ];
    }

    public function getLastIssuer()
    {

        $quote = $this->sessionCheckout->getQuote();

        $customerId = $quote->getCustomerId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerRepository = $objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);

        $customer = $customerRepository->getById($customerId);

        $customAttributes = $customer->getCustomAttributes();
        $issuerAttribute = $customAttributes['buckaroo_last_paybybank_issuer'] ?? null;

        if ($issuerAttribute) {
            return $issuerAttribute->getValue();
        } else {
            return $issuerAttribute;
        }

    }

    public function getImageUrl(string $issuerImage): string
    {
        return  $this->assetRepo->getUrl("Buckaroo_Magento2::images/ideal/{$issuerImage}.svg");
    }

    public function displayAsSelect($storeId = null): bool
    {
        return $this->scopeConfig->getValue(
            MethodPayByBank::XPATH_PAYBYBANK_SELECTION_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        ) === '2';
    }
}
