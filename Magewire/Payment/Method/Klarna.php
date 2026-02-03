<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Magewire\Payment\Method;

use Magento\Quote\Model\Quote;
use Rakit\Validation\Validator;
use Magewirephp\Magewire\Component;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\NoSuchEntityException;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna as KlarnaConfigProvider;

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

    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'customer_shipping_country_saved' => 'refresh',
        'billing_address_saved' => 'refresh',
        'customer_billing_country_saved' => 'refresh',
    ];

    protected SessionCheckout $sessionCheckout;

    protected CartRepositoryInterface $quoteRepository;

    protected KlarnaConfigProvider $klarnaConfigProvider;

    public function __construct(
        Validator $validator,
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        KlarnaConfigProvider $klarnaConfigProvider
    ) {
        parent::__construct($validator);

        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->klarnaConfigProvider = $klarnaConfigProvider;
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
     * Get magento quote
     *
     * @return Quote|null
     */
    private function getQuote(): ?Quote
    {
        try {
            return $this->sessionCheckout->getQuote();
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
        }
        return null;
    }

    /**
     * Get billing address country
     *
     * @return string|null
     */
    public function getCountryId(): ?string
    {
        $quote = $this->getQuote();
        if ($quote === null) {
            return null;
        }
        return $quote->getBillingAddress()->getCountryId();
    }

    /**
     * Check if financial warning should be shown
     * Only shown for Dutch customers when enabled in config
     *
     * @return bool
     */
    public function showFinancialWarning(): bool
    {
        return $this->getCountryId() === 'NL' && $this->klarnaConfigProvider->canShowFinancialWarning();
    }

    /**
     * Get payment method title
     *
     * @return string
     */
    public function getPaymentMethodTitle(): string
    {
        return (string) ($this->klarnaConfigProvider->getTitle() ?? 'Klarna');
    }

    /**
     * Get financial warning message for Klarna
     *
     * @return string
     */
    public function getFinancialWarningMessage(): string
    {
        $title = $this->getPaymentMethodTitle();

        return (string)__(
            'Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt, voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt maken van de diensten van %1. Door verder te gaan, accepteer je de <a target="_blank" href="%2">Algemene&nbsp;Voorwaarden</a> en bevestig je dat je de <a target="_blank" href="%3">Privacyverklaring</a> en <a target="_blank" href="%4">Cookieverklaring</a> hebt gelezen.',
            $title,
            'https://cdn.klarna.com/1.0/shared/content/legal/terms/EID/nl_nl/invoice',
            'https://cdn.klarna.com/1.0/shared/content/legal/terms/0/nl_nl/privacy',
            'https://cdn.klarna.com/1.0/shared/content/legal/terms/nl-NL/cookie_purchase'
        );
    }
}
