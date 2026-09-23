# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this module follows semantic versioning as described in the [Versioning](README.md#versioning) section of the README. Entries are ordered by version number, newest first, so 1.x maintenance releases stay with the 1.x line even when they were released after a 2.x version.

## [Unreleased]

### Added

- Click to Pay payment method (BTI-1259).

### Changed

- Improved the validation feedback for Hosted Fields (BTI-1284).
- Updated the In3 payment method logo (BTI-1163).
- Rewrote the README (BTI-1298).

### Removed

- Mandatory date of birth field for Billink (BTI-1252).
- GoSettle (formerly Knaken) payment method, which is deprecated (BTI-1388).

### Fixed

- In3 date of birth field was displayed when a company was present in the address (BTI-1130).

## [2.3.0] - 2026-06-08

Requires the Buckaroo Magento 2 plugin 2.4.0 or higher.

### Added

- Klarna (MOR) payment method (BTI-667).
- PayPal Express buttons (BP-4141).
- Sandbox environment support for PayPal Express (BTI-1005).
- Google Pay and Google Pay buttons (BTI-657).
- Bank transfer logo selection, with an International and a European logo (BTI-949).

### Changed

- Simplified the Billink checkout flow for phone number and billing name (BTI-942).
- Renamed iDEAL to the co-branded payment method name iDEAL | Wero (BTI-945).

### Removed

- Optional salutation selection step for Billink (BTI-943).
- Financial warning for Billink, which is now displayed on Billink's own page (BTI-944).

### Fixed

- Buckaroo_HyvaCheckout broke the PayPal Express button on Luma when the module was enabled (BTI-889).
- PayPal Express failed on the cart page (BTI-888, [buckaroo-it/Magento2#1596](https://github.com/buckaroo-it/Magento2/issues/1596)).
- PayPal Express buttons were visible when the PayPal method was disabled (BTI-1006).
- PayPal Express order amount of 0.1 (BTI-1007).
- Cart was not cleared after a successful PayPal Express order from the cart page (BTI-1032).
- PayPerEmail showed pronouns instead of gender in the gender field (BTI-941).

## [2.2.0] - 2026-02-03

Requires the Buckaroo Magento 2 plugin 2.3.0 or higher.

### Added

- Support for the Buckaroo Magento 2 plugin 2.3.0 and 2.3.1 (BP-5359, BP-5362).

### Fixed

- Deprecated PHP warning (Rakit Validation) caused a 500 error for multiple payment methods (BP-5369).
- Undefined constant in the `displayAsSelect` method for Creditcard and PayByBank with the selection type setting (BP-5361, [#110](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/issues/110)). Thanks to [@rickdaalhuizen90](https://github.com/rickdaalhuizen90) for reporting it and providing a fix.

## [2.1.0] - 2025-12-16

Requires the Buckaroo Magento 2 plugin 2.2.0 or higher.

### Added

- Bizum payment method (BP-5105).
- Swish payment method (BP-5104).
- Twint payment method (BP-5103).
- Wero payment method (BP-5179).

### Fixed

- Apple Pay did not work on Hyvä Checkout 1.3.3 (BP-5181, [#98](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/issues/98)).

## [2.0.0] - 2025-09-18

Requires the Buckaroo Magento 2 plugin 2.0.0 or higher.

### Added

- Tooltip for the CVC/CVV field in Hosted Fields for credit and debit cards (BP-4720).

### Changed

- Added `rakit/validation` as a Composer dependency.

### Fixed

- Riverty terms and conditions link now opens in a separate tab (BP-4515).

## [2.0.0-RC1] - 2025-06-24

Pre-release. Requires the Buckaroo Magento 2 plugin 2.0.0-RC2.

### Added

- Support for the additional Hyvä Checkout module (BP-4375).
- Financial warning for customers in the Netherlands on buy now, pay later payment methods.

### Changed

- Compatibility with the Buckaroo Magento 2 plugin 2.x (BP-3427).

### Fixed

- Buy now, pay later payment methods were not displayed in the list of payment methods (BP-4453).
- BLIK payment redirect (BP-4463).
- Buckaroo fee was returned as an integer instead of the array Hyvä Checkout expects (BP-3644).
- Orders were not placed successfully for buy now, pay later payment methods (BP-4418).
- Error when placing an order with credit and debit cards through Hosted Fields (BP-4400).

## [1.4.1] - 2025-12-12

Requires the Buckaroo Magento 2 plugin 1.55.0 or higher.

### Fixed

- Apple Pay did not work on Hyvä Checkout 1.3.3. Apple Pay is refactored for better compatibility with Hyvä Checkout, including a CSP-compatible Alpine.js component (BP-5141, BP-5150, [#98](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/issues/98)).

## [1.4.0] - 2025-06-09

Requires the Buckaroo Magento 2 plugin 1.50.1 or higher.

### Added

- Option to use Apple Pay in redirect mode through the Buckaroo Hosted Payment Page (BP-4371).
- Compatibility with the Buckaroo Magento 2 plugin 1.52.0, in which iDEAL issuer selection is removed (BP-4381).
- Support for Hyvä Theme 1.3.14, Hyvä Checkout 1.3.2 and Alpine.js CSP (BP-4412).
- B2B terms and conditions URL for allowed countries (BP-4419).

### Changed

- Updated the terms and conditions for Riverty (BP-4223).

### Fixed

- "Remaining amount" was always shown in the checkout (BP-4177).
- Unwanted spacing in the checkout (BP-4303, [#68](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/issues/68)).
- Error when placing an order with credit and debit cards through Hosted Fields (BP-4400).
- Orders were not placed successfully for buy now, pay later payment methods (BP-4418).

## [1.3.1] - 2025-03-14

Requires the Buckaroo Magento 2 plugin 1.50.1 or higher.

### Fixed

- iDEAL issuers were still visible when disabled (BP-4215).

## [1.3.0] - 2025-02-06

Requires the Buckaroo Magento 2 plugin 1.50.1 or higher.

### Changed

- Payment fee title now uses the default title, for better compatibility with third-party checkout extensions (BP-4162).

### Removed

- Checkout layout overwrite (BP-4174, [#50](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/pull/50), [#61](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/pull/61)). Thanks to [@jansentjeu](https://github.com/jansentjeu) for the contribution.
- Sofort payment method, which is discontinued (BP-3889).

### Fixed

- Billink fields were displayed incorrectly for the B2B/B2C selection (BP-4157).
- Checkout success template RequireJS JavaScript (BP-4155, [#55](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/issues/55)).
- iDEAL Fast Checkout error in Hyvä Checkout (BP-3839).
- Apple Pay did not work with Hyvä Checkout (BP-3655).
- Apple Pay error: "PaymentData" missing (BP-3811).

## [1.2.0] - 2024-09-23

Requires the Buckaroo Magento 2 plugin 1.50.1 or higher.

### Added

- Support for Hyvä Checkout 1.1.23 (BP-3715).
- Pay By Bank payment method (BP-3734).
- Additional Software header in requests (BP-3281, [#20](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/pull/20)).

### Changed

- Updated the iDEAL and In3 logos (BP-3735).

### Removed

- Giropay BIC field, which is no longer mandatory (BP-3737).

### Fixed

- Object was used as an array in `\Buckaroo\HyvaCheckout\Block\Totals\Fee::getTotal` (BP-3546, [#22](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/issues/22)).
- Grand total did not update during giftcard payments (BP-3701).
- UI issue on the payment page when logged in, in specific cases (BP-3736).
- Error "You have no items in the shopping cart" when placing an order (BP-3733).

## [1.1.2] - 2024-05-02

### Changed

- Support for PHP 8.1 and later versions in `composer.json` (BP-3545, [#23](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/pull/23)). Thanks to [Pavel Shiriaev](https://github.com/Shiriaev) and [Elze Kool](https://github.com/elzekool) for the contributions.

## [1.1.1] - 2023-12-07

### Fixed

- Klarna KP (BP-3140, [#16](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/pull/16)).

## [1.1.0] - 2023-11-28

### Fixed

- Module loaded too fast, which caused an "Unexpected identifier" error (BP-3191, [#9](https://github.com/buckaroo-it/Magento2_Hyva_Checkout/pull/9)).

## [1.0.0] - 2023-12-04

### Added

- Initial release, with support for Riverty (Afterpay), Apple Pay, Bancontact, Billink, credit and debit cards (including client-side encryption), giftcards, Giropay, iDEAL, In3, Klarna, PayPerEmail, SEPA Direct Debit, Tinka and Buckaroo Voucher.

[Unreleased]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v2.3.0...develop
[2.3.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v2.0.0-RC1...v2.0.0
[2.0.0-RC1]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v1.4.0...v2.0.0-RC1
[1.4.1]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/1.1.2...v1.2.0
[1.1.2]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/buckaroo-it/Magento2_Hyva_Checkout/releases/tag/1.0.0
