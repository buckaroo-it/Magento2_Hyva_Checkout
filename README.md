<p align="center">
  <img src="https://github.com/buckaroo-it/Magento2/assets/105488705/b00d2fcd-2458-4a8b-ab1f-e85d678a0008" width="150px" position="center">
</p>

# Buckaroo Magento Hyva Checkout

## Installation
```
mkdir app/code/Buckaroo
cd app/code/Buckaroo
git clone https://github.com/buckaroo-it/Magento2_Hyva_Checkout.git
mv Magento2_Hyva_Checkout HyvaCheckout
cd HyvaCheckout
git checkout 1.0.0-RC1
php bin/magento module:enable Buckaroo_HyvaCheckout
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```