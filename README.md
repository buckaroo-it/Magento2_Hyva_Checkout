<p align="center">
  <a href="https://www.buckaroo.nl">
    <img src="https://raw.githubusercontent.com/buckaroo-it/Media/main/Buckaroo/README.md%20Headers/buckaroo-magento2-hyva-checkout-header-rounded.png" alt="Buckaroo — Hyvä Checkout for Magento 2" width="100%">
  </a>
</p>

<h1 align="center">Buckaroo Hyvä Checkout module for Magento 2</h1>

<p align="center">
  <a href="https://packagist.org/packages/buckaroo/magento2-hyva-checkout"><img src="https://img.shields.io/packagist/v/buckaroo/magento2-hyva-checkout.svg?label=release" alt="Latest release"></a>
  <a href="https://docs.buckaroo.io/docs/magento-2-new-additional-modules-hyva-checkout-module"><img src="https://img.shields.io/badge/docs-docs.buckaroo.io-1a1a4b.svg" alt="Documentation"></a>
  <a href="https://github.com/buckaroo-it/Magento2"><img src="https://img.shields.io/badge/requires-Buckaroo%20Magento%202-1a1a4b.svg" alt="Requires the Buckaroo Magento 2 plugin"></a>
</p>

<p align="center">
  <a href="#about">About</a> &middot;
  <a href="#requirements">Requirements</a> &middot;
  <a href="#installation">Installation</a> &middot;
  <a href="#upgrade">Upgrade</a> &middot;
  <a href="#configuration">Configuration</a> &middot;
  <a href="#support">Support</a> &middot;
  <a href="#contribute">Contribute</a>
</p>

---

## About

[Hyvä](https://www.hyva.io/) is a lightweight frontend for Magento 2 that replaces the default Luma theme, cutting page weight and complexity.
Hyvä Checkout is its checkout implementation.

This module adds Buckaroo payment support to Hyvä Checkout. It is an extension of the [Buckaroo Magento 2 plugin](https://github.com/buckaroo-it/Magento2), not a replacement for it — the main plugin handles the payments, and this module makes them work inside the Hyvä Checkout flow.

> [!IMPORTANT]
> There are two Hyvä checkout products, and they need different modules.<br>
This repository is for **Hyvä Checkout**. If you use **Hyvä React Checkout**, install [Magento2_Hyva](https://github.com/buckaroo-it/Magento2_Hyva) instead.

[Full module documentation on docs.buckaroo.io](https://docs.buckaroo.io/docs/magento-2-new-additional-modules-hyva-checkout-module)

---

## Requirements

| Requirement | Supported versions |
|---|---|
| Buckaroo Magento 2 plugin | 2.0.0 or higher |
| Hyvä Checkout | 1.1.8 or higher |

Magento, PHP and Composer requirements come from the [main plugin](https://github.com/buckaroo-it/Magento2). You also need a Buckaroo account — don't have one yet? [Request an account](https://www.buckaroo.nl/start).

---

## Installation

Run the following commands from your Magento 2 root folder:

```bash
composer require buckaroo/magento2-hyva-checkout
php bin/magento module:enable Buckaroo_HyvaCheckout
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

---

## Upgrade

```bash
composer update buckaroo/magento2-hyva-checkout
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

> [!TIP]
> Always test an upgrade on a staging environment first and check the [release notes](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/releases) for breaking changes.

---

## Configuration

This module has no settings of its own. Payment methods are configured in the main Buckaroo plugin, under **Stores → Configuration → Sales → Buckaroo** in the Magento admin.

Once the module is installed and Hyvä Checkout is active, the payment methods you enabled there appear in the Hyvä checkout.

Step-by-step instructions: [Configuring the Magento 2 plugin](https://docs.buckaroo.io/docs/magento-2-configuration)

---

## Support

Having trouble? Work through this list before reaching out:

1. Confirm the [main Buckaroo plugin](https://github.com/buckaroo-it/Magento2) is installed, active and correctly configured, and that the payment methods work outside Hyvä Checkout.
2. Check that you installed the right module for your checkout — this one for Hyvä Checkout, [Magento2_Hyva](https://github.com/buckaroo-it/Magento2_Hyva) for Hyvä React Checkout.
3. Confirm you are on the [latest release](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/releases) of both this module and the main plugin.
4. Clear the cache and redeploy static content after installing or upgrading.

Still stuck? Contact us and include your Magento version, Hyvä Checkout version, main plugin version, this module's version and the relevant log lines.

- **Bug reports and feature requests:** [open an issue](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/issues)
- **Technical support:** [support@buckaroo.nl](mailto:support@buckaroo.nl)
- **Phone:** +31 (0)30 711 50 50
- **Gateway status:** [status.buckaroo.io](https://status.buckaroo.io/)

---

## Contribute

We really appreciate it when developers help improve the Buckaroo plugins. Please read our [Contribution Guidelines](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/blob/main/CONTRIBUTING.md) before opening a pull request.

Found a security issue? Please report it privately to [support@buckaroo.nl](mailto:support@buckaroo.nl) instead of opening a public issue.

---

## Versioning

We follow semantic versioning (`MAJOR.MINOR.PATCH`):

- **MAJOR** — breaking changes that require additional testing and caution.
- **MINOR** — new functionality with limited impact.
- **PATCH** — bug fixes and hotfixes only.

All changes are documented on the [releases page](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/releases).

---

<p align="center">
  <sub>Made with care by <a href="https://www.buckaroo.nl">Buckaroo</a>.<br>
  This document is subject to change; typos and language errors are possible.</sub>
</p>
