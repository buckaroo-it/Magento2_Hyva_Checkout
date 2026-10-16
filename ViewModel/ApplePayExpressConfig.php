<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\ViewModel;

use Buckaroo\Magento2\Block\Catalog\Product\View\Applepay;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Config for the Hyvä Apple Pay express button on the product and cart page.
 * Only the values the Apple Pay sheet needs are exposed.
 */
class ApplePayExpressConfig implements ArgumentInterface
{
    private const PUBLIC_KEYS = [
        'storeName',
        'currency',
        'country',
        'cultureCode',
        'guid',
        'buttonStyle',
        'supportedNetworks',
    ];

    private Registry $registry;

    private CheckoutSession $checkoutSession;

    private UrlInterface $urlBuilder;

    private FormKey $formKey;

    public function __construct(
        Registry $registry,
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder,
        FormKey $formKey
    ) {
        $this->registry = $registry;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
    }

    /**
     * @param Applepay $block
     * @param string   $page Product|Cart
     *
     * @return array
     */
    public function getConfig(Applepay $block, string $page): array
    {
        $applepay = json_decode((string)$block->getApplepayConfig(), true);
        $applepay = $applepay['payment']['buckaroo']['buckaroo_magento2_applepay'] ?? [];

        $config = array_intersect_key($applepay, array_flip(self::PUBLIC_KEYS));
        $config['page'] = $page === 'Cart' ? 'cart' : 'product';
        $config['baseUrl'] = $this->urlBuilder->getUrl('', ['_secure' => true]);
        $config['formKey'] = $this->formKey->getFormKey();
        $config['amount'] = $page === 'Cart' ? $this->getCartTotal() : $this->getProductUnitPrice();

        return $config;
    }

    /**
     * Whether the button should be rendered for this page
     *
     * @param Applepay $block
     * @param string   $page Product|Cart
     *
     * @return bool
     */
    public function canShow(Applepay $block, string $page): bool
    {
        if (!$block->canShowButton($page) || $block->isIdinVerificationRequired()) {
            return false;
        }

        return $page !== 'Cart' || (float)$this->checkoutSession->getQuote()->getItemsCount() > 0;
    }

    private function getProductUnitPrice(): float
    {
        $product = $this->registry->registry('product');

        if (!$product instanceof Product || !$product->getId()) {
            return 0.0;
        }

        try {
            return (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function getCartTotal(): float
    {
        try {
            return (float)$this->checkoutSession->getQuote()->getGrandTotal();
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
