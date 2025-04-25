/* BEGIN ########################################################### */
/* Added: */
/* eslint-disable */
/* END ############################################################# */
/*!
 * Buckaroo Client SDK v1.8.2
 *
 * Copyright Buckaroo
 * Released under the MIT license
 * https://buckaroo.nl
 *
 * Date: 2024-10-24 11:26
 */

(function (window) {
    'use strict';

    // Don’t clobber an existing instance (e.g. if the script is injected twice).
    if (window.BuckarooApplePay) {
        console.warn('[BuckarooApplePay] SDK already present – skipped re‑definition');
        return;
    }

    /**
     * Lightweight feature‑check for Apple Pay and the active card of the user.
     * Returns a boolean.
     */
    const checkPaySupport = async (merchantIdentifier) => {
        if (!('ApplePaySession' in window)) return false;
        if (typeof ApplePaySession === 'undefined') return false;
        return ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
    };

    /**
     * Build the CSS‑class list for the Apple Pay button.
     */
    const getButtonClass = (buttonStyle = 'black', buttonType = 'plain') => {
        const classes = ['apple-pay', 'apple-pay-button'];

        switch (buttonType) {
            case 'book':
                classes.push('apple-pay-button-type-book');
                break;
            case 'buy':
                classes.push('apple-pay-button-type-buy');
                break;
            case 'check-out':
                classes.push('apple-pay-button-type-check-out');
                break;
            case 'donate':
                classes.push('apple-pay-button-type-donate');
                break;
            case 'set-up':
                classes.push('apple-pay-button-type-set-up');
                break;
            case 'subscribe':
                classes.push('apple-pay-button-type-subscribe');
                break;
            case 'plain':
            default:
                classes.push('apple-pay-button-type-plain');
        }

        switch (buttonStyle) {
            case 'white':
                classes.push('apple-pay-button-white');
                break;
            case 'white-outline':
                classes.push('apple-pay-button-white-with-line');
                break;
            case 'black':
            default:
                classes.push('apple-pay-button-black');
        }

        return classes.join(' ');
    };

    /**
     * The main Apple‑Pay payment helper.
     */
    class PayPayment {
        applePayVersion = 4;
        validationUrl = 'https://applepay.buckaroo.io/v1/request-session';
        session = null;

        constructor(options) {
            this.options = options;
            this.init();
            this.validate();
        }

        /** Inject Buckaroo CSS and confirm Apple Pay availability. */
        async init() {
            const supported = await checkPaySupport(this.options.merchantIdentifier);
            if (!supported) return;
            if (!document.getElementById('buckaroo-sdk-css')) {
                document.head.insertAdjacentHTML(
                    'beforeend',
                    '<link id="buckaroo-sdk-css" href="https://checkout.buckaroo.nl/api/buckaroosdk/css" rel="stylesheet">'
                );
            }
        }

        /** Abort the current ApplePaySession without throwing when none exists. */
        abortSession() {
            if (this.session && typeof this.session.abort === 'function') {
                this.session.abort();
            }
        }

        /** Ensure mandatory fields are present before we start. */
        validate() {
            const required = [
                'processCallback',
                'storeName',
                'countryCode',
                'currencyCode',
                'merchantIdentifier',
            ];
            required.forEach((key) => {
                if (!this.options[key]) {
                    console.error(`[BuckarooApplePay] option \u201C${key}\u201D is missing`);
                    throw new Error(`ApplePay: ${key} is not set`);
                }
            });
        }

        /** Kick off the payment sheet. */
        beginPayment() {
            const paymentRequest = {
                countryCode: this.options.countryCode,
                currencyCode: this.options.currencyCode,
                merchantCapabilities: this.options.merchantCapabilities,
                supportedNetworks: this.options.supportedNetworks,
                lineItems: this.options.lineItems,
                total: this.options.totalLineItem,
                requiredBillingContactFields: this.options.requiredBillingContactFields,
                requiredShippingContactFields: this.options.requiredShippingContactFields,
                shippingType: this.options.shippingType,
                shippingMethods: this.options.shippingMethods,
            };

            this.session = new ApplePaySession(this.applePayVersion, paymentRequest);

            // Bind handlers.
            this.session.onvalidatemerchant = this.onValidateMerchant.bind(this);
            this.session.onpaymentauthorized = this.onPaymentAuthorized.bind(this);
            if (this.options.shippingMethodSelectedCallback) {
                this.session.onshippingmethodselected = this.onShippingMethodSelected.bind(this);
            }
            if (this.options.shippingContactSelectedCallback) {
                this.session.onshippingcontactselected = this.onShippingContactSelected.bind(this);
            }
            if (this.options.cancelCallback) {
                this.session.oncancel = this.onCancel.bind(this);
            }

            this.session.begin();
        }

        /**
         * Merchant‑validation: exchange the validation URL for an Apple session.
         */
        async onValidateMerchant(event) {
            const payload = {
                validationUrl: event.validationURL,
                displayName: this.options.storeName,
                domainName: window.location.hostname,
                merchantIdentifier: this.options.merchantIdentifier,
            };

            try {
                const res = await fetch(this.validationUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });

                if (!res.ok) throw new Error(`Buckaroo returned status ${res.status}`);
                let session = await res.json();

                // Buckaroo sometimes returns a JSON‑encoded string or wraps the object.
                if (typeof session === 'string') session = JSON.parse(session);
                if (session && session.result) session = session.result;

                this.session.completeMerchantValidation(session);
            } catch (err) {
                console.error('[BuckarooApplePay] Merchant validation failed:', err);
                this.abortSession();
            }
        }

        /** Handle the encrypted token once the customer authorises payment. */
        async onPaymentAuthorized(event) {
            try {
                const authorizationResult = await this.options.processCallback(event.payment);
                this.session.completePayment(authorizationResult);
            } catch (err) {
                console.error('[BuckarooApplePay] processCallback threw:', err);
                this.session.completePayment(ApplePaySession.STATUS_FAILURE);
            }
        }

        async onShippingMethodSelected(event) {
            if (!this.options.shippingMethodSelectedCallback) return;
            const result = await this.options.shippingMethodSelectedCallback(event.shippingMethod);
            if (result) this.session.completeShippingMethodSelection(result);
        }

        async onShippingContactSelected(event) {
            if (!this.options.shippingContactSelectedCallback) return;
            const result = await this.options.shippingContactSelectedCallback(event.shippingContact);
            if (result) this.session.completeShippingContactSelection(result);
        }

        onCancel(event) {
            if (this.options.cancelCallback) this.options.cancelCallback(event);
        }
    }

    /** Convenience wrapper that mirrors Buckaroo’s original constructor signature. */
    function PayOptions(
        storeName,
        countryCode,
        currencyCode,
        cultureCode,
        merchantIdentifier,
        lineItems,
        totalLineItem,
        shippingType,
        shippingMethods,
        processCallback,
        shippingMethodSelectedCallback = null,
        shippingContactSelectedCallback = null,
        requiredBillingContactFields = ['email', 'name', 'postalAddress'],
        requiredShippingContactFields = ['email', 'name', 'postalAddress'],
        cancelCallback = null,
        merchantCapabilities = ['supports3DS', 'supportsCredit', 'supportsDebit'],
        supportedNetworks = ['masterCard', 'visa', 'maestro', 'vPay', 'cartesBancaires', 'privateLabel']
    ) {
        this.storeName = storeName;
        this.countryCode = countryCode;
        this.currencyCode = currencyCode;
        this.cultureCode = cultureCode;
        this.merchantIdentifier = merchantIdentifier;
        this.lineItems = lineItems;
        this.totalLineItem = totalLineItem;
        this.shippingType = shippingType;
        this.shippingMethods = shippingMethods;
        this.processCallback = processCallback;
        this.shippingMethodSelectedCallback = shippingMethodSelectedCallback;
        this.shippingContactSelectedCallback = shippingContactSelectedCallback;
        this.requiredBillingContactFields = requiredBillingContactFields;
        this.requiredShippingContactFields = requiredShippingContactFields;
        this.cancelCallback = cancelCallback;
        this.merchantCapabilities = merchantCapabilities;
        this.supportedNetworks = supportedNetworks;
    }

    // Export a single namespace to the page.
    window.BuckarooApplePay = {
        PayPayment,
        PayOptions,
        checkPaySupport,
        getButtonClass,
    };
})(window);
