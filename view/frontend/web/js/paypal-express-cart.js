/**
 * PayPal Express - cart page (Hyvä, no RequireJS).
 */
(function () {
    'use strict';

    var containerSelector = '#buckaroo-paypal-express-cart';
    var hyvaPaypal = window.BuckarooHyvaPaypalExpress;
    var result = null;
    var cartId = null;
    var config = null;
    var baseUrl = '';
    var restBase = '';

    function buildUrl(path) {
        return restBase + path;
    }

    function displayErrorMessage(message) {
        var errorText = typeof message === 'string'
            ? message
            : (message && message.message) || 'Cannot create payment';

        var errorEl = document.getElementById('paypal-validation-error-cart-hyva');
        if (errorEl) {
            errorEl.textContent = errorText;
            errorEl.style.display = 'block';
        }
    }

    var options = {
        containerSelector: containerSelector,

        createPaymentHandler: function (orderID) {
            return hyvaPaypal.post(buildUrl('/buckaroo/paypal-express/order/create'), {
                paypal_order_id: orderID,
                cart_id: cartId
            }).then(function (response) {
                result = response;
                return response;
            });
        },

        onShippingChangeHandler: function (data, actions) {
            return hyvaPaypal.post(buildUrl('/buckaroo/paypal-express/quote/create'), {
                shipping_address: data.shipping_address,
                order_data: '',
                page: 'cart'
            }).then(function (response) {
                if (response.message) {
                    return Promise.reject(response.message);
                }

                cartId = response.cart_id;

                var newTotal = parseFloat(response.value);
                var baseAmount = response.breakdown && response.breakdown.item_total
                    ? parseFloat(response.breakdown.item_total.value)
                    : newTotal;
                var shippingCost = response.breakdown && response.breakdown.shipping
                    ? parseFloat(response.breakdown.shipping.value)
                    : 0;

                return actions.order.patch([
                    {
                        op: 'replace',
                        path: "/purchase_units/@reference_id=='default'/amount",
                        value: {
                            currency_code: options.currency,
                            value: newTotal.toFixed(2),
                            breakdown: {
                                item_total: {
                                    currency_code: options.currency,
                                    value: baseAmount.toFixed(2)
                                },
                                shipping: {
                                    currency_code: options.currency,
                                    value: shippingCost.toFixed(2)
                                }
                            }
                        }
                    }
                ]).catch(function () {
                    // Ignore patch failure
                });
            });
        },

        onSuccessCallback: function () {
            if (result && result.message) {
                displayErrorMessage(result.message);
            } else if (result && result.cart_id) {
                if (hyvaPaypal.reloadCustomerSectionData) {
                    hyvaPaypal.reloadCustomerSectionData();
                }
                window.location.replace(baseUrl + '/checkout/onepage/success/');
            } else {
                displayErrorMessage('Cannot create payment');
            }
        },

        onErrorCallback: displayErrorMessage,

        onCancelCallback: function () {
            displayErrorMessage('You have canceled the payment request.');
        }
    };

    window.BuckarooHyvaCheckoutPaypalExpressCartInit = function () {
        config = window.BuckarooHyvaCheckoutPaypalExpressCartConfig;

        if (!config || !hyvaPaypal || !window.BuckarooSdk || !window.BuckarooSdk.PayPal) {
            return;
        }

        if (config.isTestMode !== undefined && window.BuckarooSdk.Base && window.BuckarooSdk.Base.setTestMode) {
            window.BuckarooSdk.Base.setTestMode(!!config.isTestMode);
        }

        baseUrl = (config.baseUrl || '').replace(/\/?$/, '');
        restBase = baseUrl + '/rest/V1';

        var amount = hyvaPaypal.parseAmount(config.amount);
        if (!amount) {
            displayErrorMessage('Unable to initialize PayPal Express: cart total not available.');
            return;
        }

        options.buckarooWebsiteKey = config.buckarooWebsiteKey || '';
        options.paypalMerchantId = config.paypalMerchantId || '';
        options.currency = config.currency || 'EUR';
        options.amount = amount.toFixed(2);
        options.page = 'cart';
        options.isTestMode = !!config.isTestMode;

        if (config.style) {
            options.style = config.style;
        }

        var container = document.querySelector(containerSelector);
        if (!container || container.hasAttribute('data-buckaroo-paypal-rendered')) {
            return;
        }

        container.setAttribute('data-buckaroo-paypal-rendered', '1');
        window.BuckarooSdk.PayPal.initiate(options);
    };
})();
