/**
 * PayPal Express - cart page standalone init (no RequireJS).
 * Mirrors Buckaroo_Magento2/js/view/checkout/paypal-express/pay.js for cart context.
 */
(function () {
    'use strict';

    var containerSelector = '#buckaroo-paypal-express-cart';
    var result = null;
    var cartId = null;

    var config = null;
    var baseUrl = '';
    var restBase = '';

    function buildUrl(path) {
        return restBase + path;
    }

    function post(url, data) {
        if (typeof window.jQuery !== 'undefined' && window.jQuery.post) {
            return new Promise(function(resolve, reject) {
                window.jQuery.post(url, data)
                    .done(resolve)
                    .fail(function(xhr) {
                        var err = new Error(
                            xhr.responseJSON && xhr.responseJSON.message
                                ? xhr.responseJSON.message
                                : 'Request failed'
                        );
                        err.response = xhr;
                        err.body = xhr.responseJSON;
                        reject(err);
                    });
            });
        }

        var params = new URLSearchParams();

        if (typeof data === 'object') {
            Object.keys(data).forEach(function(key) {
                var value = data[key];
                if (value === null || value === undefined) return;

                if (typeof value === 'object' && !Array.isArray(value)) {
                    Object.keys(value).forEach(function(nestedKey) {
                        var nestedValue = value[nestedKey];
                        if (nestedValue !== null && nestedValue !== undefined && nestedValue !== '') {
                            params.append(key + '[' + nestedKey + ']', String(nestedValue));
                        }
                    });
                } else {
                    params.append(key, String(value));
                }
            });
        }

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params.toString(),
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (responseBody) {
                    var msg = responseBody && responseBody.message
                        ? responseBody.message
                        : 'Request failed';
                    var err = new Error(msg);
                    err.response = res;
                    err.body = responseBody;
                    throw err;
                });
            }
            return res.json();
        });
    }

    function getOrderData() {
        return '';
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
            return post(buildUrl('/buckaroo/paypal-express/order/create'), {
                paypal_order_id: orderID,
                cart_id: cartId
            }).then(function (response) {
                result = response;
                return response;
            });
        },

        onShippingChangeHandler: function (data, actions) {
            var payload = {
                shipping_address: data.shipping_address,
                order_data: getOrderData(),
                page: 'cart'
            };

            return post(buildUrl('/buckaroo/paypal-express/quote/create'), payload)
                .then(function (response) {

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
                window.location.replace(baseUrl + '/checkout/onepage/success/');
            } else {
                displayErrorMessage('Cannot create payment');
            }
        },

        onErrorCallback: function (reason) {
            displayErrorMessage(reason);
        },

        onCancelCallback: function () {
            displayErrorMessage('You have canceled the payment request.');
        }
    };

    window.BuckarooHyvaCheckoutPaypalExpressCartInit = function () {

        config = window.BuckarooHyvaCheckoutPaypalExpressCartConfig;
        if (!config) return;

        if (!window.BuckarooSdk || !window.BuckarooSdk.PayPal) return;

        baseUrl = (config.baseUrl || '').replace(/\/?$/, '');
        restBase = baseUrl + '/rest/V1';

        options.buckarooWebsiteKey = config.buckarooWebsiteKey || '';
        options.paypalMerchantId = config.paypalMerchantId || '';
        options.currency = config.currency || 'EUR';
        options.amount = parseFloat(config.amount, 10) || 0.01;
        if (isNaN(options.amount) || options.amount <= 0) {
            options.amount = 0.01;
        }
        options.page = 'cart';

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
