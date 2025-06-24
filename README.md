<p align="center">
  <img src="https://www.buckaroo.nl/media/33gf24ru/magento2_hyvacheckout_icon.png" width="150px" position="center">
</p>

# Buckaroo Magento 2 Hyvä Checkout
Buckaroo has developed a cutting-edge Hyvä Checkout module as an extension for the Buckaroo Magento 2 plugin. By integrating this module, we have seamlessly incorporated support for Hyvä Checkout, enhancing your payment experience with Buckaroo while enjoying quicker loading times, particularly on mobile devices – a remarkable 13 times faster. With Hyvä's outstanding performance, your store will witness improved conversion rates, superior performance, and reduced overall complexity.

#### Please note that there are 2 versions of Hyvä modules:
* If you are using the **Hyvä Checkout module**, you can then use this repository:<br>
[https://github.com/buckaroo-it/Magento2_Hyva_Checkout](https://github.com/buckaroo-it/Magento2_Hyva_Checkout)

* If you are using the **Hyvä React Checkout module**, you'll need to use a separate repository for that:<br>
[https://github.com/buckaroo-it/Magento2_Hyva](https://github.com/buckaroo-it/Magento2_Hyva)
<br>

## Requirements
* [Buckaroo Magento 2 plugin](https://github.com/buckaroo-it/Magento2/releases) version v2.0.0-RC2
* Hyvä Checkout version 1.1.8 or higher
<br>

## Installation
**Install the module by using composer with the following commands:**

1. Require the composer package:
```
composer require buckaroo/magento2-hyva-checkout
```
2. Enable the magento module, run setup upgrade & deploy static content:
```
php bin/magento module:enable Buckaroo_HyvaCheckout
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```
