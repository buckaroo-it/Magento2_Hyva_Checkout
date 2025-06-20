<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\NoSuchEntityException;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna as MethodConfigProvider;

class Klarna extends Component\Form implements EvaluationInterface
{
    public ?string $gender = null;

    protected $loader = [
        'gender' => 'Saving gender'
    ];

    protected $rules = [
        'gender' => 'required'
    ];

    protected $messages = [
        'gender:required' => 'The gender is required'
    ];

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

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $this->gender  = $this->sessionCheckout
            ->getQuote()
            ->getPayment()
            ->getAdditionalInformation('customer_gender');
    }

    /**
     * Listen for bank gender been updated.
     */
    public function updatedGender(string $value): ?string
    {
        $value = empty($value) ? null : $value;
        try {
            $quote = $this->sessionCheckout->getQuote();
            $quote->getPayment()->setAdditionalInformation('customer_gender', $value);

            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }

        return $value;
    }
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->gender === null) {
            return $resultFactory->createErrorMessageEvent()
                ->withCustomEvent('payment:method:error')
                ->withMessage('The gender is required');
        }

        return $resultFactory->createSuccess();
    }

    public function getGenderList(): array
    {
        return [
            ['code' => 'male', 'name' => __('He/him')],
            ['code' => 'female', 'name' => __('She/her')]
        ];
    }

    /**
     * Get payment method title
     *
     * @return string
     */
    public function getPaymentMethodTitle(): string
    {
        return $this->methodConfigProvider->getTitle() ?: 'Klarna: Pay later';
    }

    /**
     * Show financial warning for Netherlands customers
     *
     * @return bool
     */
    public function showFinancialWarning(): bool
    {
        $quote = $this->getQuote();

        if ($quote === null) {
            return false;
        }

        $billingAddress = $quote->getBillingAddress();

        return $billingAddress !== null &&
               $billingAddress->getCountryId() === 'NL' &&
               $this->methodConfigProvider->canShowFinancialWarning();
    }

    /**
     * Get quote from session
     *
     * @return \Magento\Quote\Model\Quote|null
     */
    private function getQuote(): ?\Magento\Quote\Model\Quote
    {
        try {
            return $this->sessionCheckout->getQuote();
        } catch (LocalizedException $e) {
            return null;
        }
    }
}
