/**
 * PayPal Express - product page (Hyvä).
 */
(function () {
    'use strict';

    var containerSelector = '#buckaroo-paypal-express-product';
    var productPriceMixin = window.BuckarooExpressProductPrice;
    var hyvaPaypal = window.BuckarooHyvaPaypalExpress;

    if (!productPriceMixin || !hyvaPaypal) {
        console.error('[PayPal Express] Required scripts are not loaded');
        return;
    }

    var paypalExpress = Object.assign({}, productPriceMixin, {
        page: 'product',
        result: null,
        cart_id: null,
        options: null,
        baseUrl: '',
        restBase: '',

        resolveProductPrice: function (config) {
            var serverAmount = hyvaPaypal.parseAmount(config.amount);
            var productPrice = this.getProductTotalPrice();

            if ((!productPrice || productPrice <= 0) && serverAmount) {
                productPrice = serverAmount;
                this.productSelected.unitPrice = serverAmount / (this.productSelected.qty || 1);
            }

            return productPrice;
        },

        setConfig: function (config) {
            var self = this;
            this.page = 'product';

            if (config.isTestMode !== undefined && window.BuckarooSdk && window.BuckarooSdk.Base) {
                window.BuckarooSdk.Base.setTestMode(config.isTestMode);
            }

            this.onProductPriceChange = function (total) {
                if (self.options && total > 0) {
                    self.options.amount = total.toFixed(2);
                }
            };

            this.initProductPriceWatchers();

            var productPrice = this.resolveProductPrice(config);
            if (!productPrice || productPrice <= 0) {
                this.displayErrorMessage(
                    'Unable to initialize PayPal Express: Product price not available. Please refresh the page and try again.'
                );
                return;
            }

            this.options = Object.assign({}, config, {
                containerSelector: containerSelector,
                amount: productPrice.toFixed(2),
                createPaymentHandler: this.createPaymentHandler.bind(this),
                onShippingChangeHandler: this.onShippingChangeHandler.bind(this),
                onSuccessCallback: this.onSuccessCallback.bind(this),
                onErrorCallback: this.onErrorCallback.bind(this),
                onCancelCallback: this.onCancelCallback.bind(this),
                onInitCallback: function () {},
                onClickCallback: function () {
                    self.result = null;
                },
                onValidationCallback: this.validateBeforePaypalOrder.bind(this)
            });
        },

        init: function () {
            if (!window.BuckarooSdk || !window.BuckarooSdk.PayPal || !window.BuckarooSdk.PayPal.initiate || !this.options) {
                return;
            }

            var container = document.querySelector(containerSelector);
            if (!container || container.hasAttribute('data-buckaroo-paypal-rendered')) {
                return;
            }

            container.setAttribute('data-buckaroo-paypal-rendered', '1');
            window.BuckarooSdk.PayPal.initiate(this.options);
        },

        onShippingChangeHandler: function (data, actions) {
            var self = this;

            if (typeof window.jQuery !== 'undefined') {
                var form = window.jQuery('#product_addtocart_form');
                if (form.length && typeof form.valid === 'function' && form.valid() === false) {
                    return actions.reject();
                }
            }

            return hyvaPaypal.post(this.restBase + '/buckaroo/paypal-express/quote/create', {
                shipping_address: data.shipping_address,
                order_data: this.getOrderData(),
                page: 'product'
            }).then(function (response) {
                if (response.message) {
                    return Promise.reject(response.message);
                }

                self.cart_id = response.cart_id;

                var newTotal = parseFloat(response.value);
                if (Number.isNaN(newTotal)) {
                    return Promise.reject('Cannot update payment totals');
                }

                var currency = self.options.currency;
                var bd = response.breakdown || {};
                var itemTotal = bd.item_total ? parseFloat(bd.item_total.value) : newTotal;
                var shippingAmt = bd.shipping ? parseFloat(bd.shipping.value) : 0;
                var taxTotal = bd.tax_total ? parseFloat(bd.tax_total.value) : 0;

                if (Number.isNaN(itemTotal)) {
                    itemTotal = newTotal;
                }
                if (Number.isNaN(shippingAmt)) {
                    shippingAmt = 0;
                }
                if (Number.isNaN(taxTotal)) {
                    taxTotal = 0;
                }

                return actions.order.patch([
                    {
                        op: 'replace',
                        path: "/purchase_units/@reference_id=='default'/amount",
                        value: {
                            currency_code: currency,
                            value: newTotal.toFixed(2),
                            breakdown: {
                                item_total: {
                                    currency_code: currency,
                                    value: itemTotal.toFixed(2)
                                },
                                shipping: {
                                    currency_code: currency,
                                    value: shippingAmt.toFixed(2)
                                },
                                tax_total: {
                                    currency_code: currency,
                                    value: taxTotal.toFixed(2)
                                }
                            }
                        }
                    }
                ]).then(function () {
                    self.options.amount = newTotal.toFixed(2);
                }).catch(function () {
                    self.options.amount = newTotal.toFixed(2);
                });
            });
        },

        createPaymentHandler: function (orderID) {
            var self = this;

            return hyvaPaypal.post(this.restBase + '/buckaroo/paypal-express/order/create', {
                paypal_order_id: orderID,
                cart_id: this.cart_id
            }).then(function (response) {
                self.result = response;
                return response;
            });
        },

        onSuccessCallback: function () {
            if (this.result && this.result.message) {
                this.displayErrorMessage(this.result.message);
                return;
            }

            if (this.result && this.result.cart_id && this.result.cart_id.length) {
                if (hyvaPaypal.reloadCustomerSectionData) {
                    hyvaPaypal.reloadCustomerSectionData();
                }
                window.location.replace(this.baseUrl + '/checkout/onepage/success/');
            } else {
                this.displayErrorMessage('Cannot create payment');
            }
        },

        onErrorCallback: function (reason) {
            this.displayErrorMessage(reason);
        },

        onCancelCallback: function () {
            this.displayErrorMessage('You have canceled the payment request.');
        },

        getOrderData: function () {
            var form = document.getElementById('product_addtocart_form');
            if (!form) {
                return '';
            }

            if (typeof window.jQuery !== 'undefined') {
                var $form = window.jQuery(form);
                if ($form.length) {
                    return $form.serialize();
                }
            }

            return new URLSearchParams(new FormData(form)).toString();
        },

        displayErrorMessage: function (message) {
            var errorText = typeof message === 'string'
                ? message
                : (message && message.message) || 'Cannot create payment';

            var errorEl = document.getElementById('paypal-validation-error-hyva');
            if (errorEl) {
                errorEl.textContent = errorText;
                errorEl.style.display = 'block';
            }
        },

        validateBeforePaypalOrder: function () {
            var optionsResult = this.validateConfigurableOptions();
            if (optionsResult !== true) {
                return optionsResult;
            }

            var total = this.getProductTotalPrice();
            if (!total || total <= 0) {
                return {
                    isValid: false,
                    message: 'Please select all product options before continuing.'
                };
            }

            this.options.amount = total.toFixed(2);
            return true;
        }
    });

    window.BuckarooHyvaCheckoutPaypalExpressInit = function () {
        var config = window.BuckarooHyvaCheckoutPaypalExpressConfig;
        if (!config) {
            return;
        }

        paypalExpress.baseUrl = (config.baseUrl || '').replace(/\/?$/, '');
        paypalExpress.restBase = paypalExpress.baseUrl + '/rest/V1';
        paypalExpress.setConfig(config);
        paypalExpress.init();
    };
})();
