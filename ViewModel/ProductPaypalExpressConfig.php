<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\ViewModel;

use Buckaroo\Magento2\Block\Catalog\Product\View\PaypalExpress;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Supplies Hyvä PDP PayPal Express config including server-side product price.
 */
class ProductPaypalExpressConfig implements ArgumentInterface
{
    /**
     * @var Registry
     */
    private $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param PaypalExpress $block
     * @return array
     */
    public function getConfig(PaypalExpress $block): array
    {
        $config = $block->getConfig();
        $unitPrice = $this->getProductUnitPrice();

        if ($unitPrice !== null) {
            $config['amount'] = number_format($unitPrice, 2, '.', '');
        }

        return $config;
    }

    /**
     * @return float|null
     */
    private function getProductUnitPrice(): ?float
    {
        $product = $this->registry->registry('product');

        if (!$product instanceof Product || !$product->getId()) {
            return null;
        }

        try {
            $value = (float)$product->getPriceInfo()
                ->getPrice('final_price')
                ->getAmount()
                ->getValue();

            return $value > 0 ? $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
