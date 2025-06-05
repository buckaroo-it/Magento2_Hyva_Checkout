<?php

namespace Buckaroo\HyvaCheckout\Plugin;

use Magento\Framework\View\Element\Template;
use Hyva\Checkout\Model\MethodMetaDataInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Model\MethodInterface as PaymentMethodInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Hyva\Checkout\ViewModel\Checkout\Payment\MethodList as HyvaMethodList;
use Hyva\Checkout\Model\MethodMetaDataFactory;

class MethodList implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    protected Factory $configProviderMethodFactory;

    protected MethodMetaDataFactory $methodMetaDataFactory;

    public function __construct(
        Factory $configProviderMethodFactory,
        MethodMetaDataFactory $methodMetaDataFactory
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->methodMetaDataFactory = $methodMetaDataFactory;
    }

    public function aroundGetMethodMetaData(
        HyvaMethodList $methodList,
        callable $proceed,
        Template $parent,
        PaymentMethodInterface $method
    ): MethodMetaDataInterface {

        $methodCode = $method->getCode();
        if(strpos($methodCode, "buckaroo_magento2_") !== false) {
            $arguments = [
                'icon' => [
                    'svg' => $this->getSvgLogo($methodCode),
                ],
                'subtitle' => $this->getSubtitle($methodCode)
            ];
            return $this->methodMetaDataFactory->create(['data' => $arguments ?? [], 'method' => $method]);
        }

        return $proceed($parent, $method);
    }

    private function getSvgLogo(string $methodCode): string
    {
        $method = str_replace("buckaroo_magento2_", "", $methodCode);
        $mappings = [
            "afterpay2" => "svg/afterpay.svg",
            "afterpay20" => "svg/afterpay.svg",
            "capayablein3" => "svg/ideal-in3.svg",
            "capayablepostpay" => "svg/ideal-in3.svg",
            "creditcard" => "svg/creditcards.svg",
            "creditcards" => "svg/creditcards.svg",
            "giftcards" => "svg/giftcards.svg",
            "idealprocessing" => "svg/ideal.svg",
            "klarnain" => "svg/klarna.svg",
            "klarnakp" => "svg/klarna.svg",
            "mrcash" => "svg/bancontact.svg",
            "p24" => "svg/przelewy24.svg",
            "sepadirectdebit" => "svg/sepa-directdebit.svg",
            "emandate" => "emandate.png",
            "pospayment" => "pos.png",
            "transfer" => "svg/sepa-credittransfer.svg",
            "voucher" => "svg/vouchers.svg",
            "paybybank" => "paybybank.gif",
            "knaken" => "svg/goSettle.svg",
        ];

        $name = "svg/{$method}.svg";

        if(isset($mappings[$method])) {
            $name = $mappings[$method];
        }

        return "Buckaroo_Magento2::images/{$name}";
    }

    private function getSubtitle(string $methodCode): ?string
    {
        return $this->getConfig($methodCode)->getSubtext();
    }


    private function getConfig(string $methodCode): ConfigProviderInterface
    {
        return $this->configProviderMethodFactory->get($methodCode);
    }
}
