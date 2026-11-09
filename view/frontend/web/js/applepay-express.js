/**
 * Apple Pay express button for the Hyvä product and cart page.
 *
 * Same flow as the Luma express button (Buckaroo_Magento2 applepay/pay.js), without RequireJS:
 * the Apple Pay sheet is opened from Apple's <apple-pay-button>, the quote is built through the
 * buckaroo/applepay/* endpoints and the order is placed by buckaroo/applepay/saveOrder.
 */
(function (window, document) {
    'use strict';

    var READY_ATTEMPTS = 100;
    var READY_DELAY_MS = 100;

    function whenReady(isReady, callback, attempt) {
        attempt = attempt || 0;
        if (isReady()) {
            callback();
            return;
        }
        if (attempt < READY_ATTEMPTS) {
            setTimeout(function () {
                whenReady(isReady, callback, attempt + 1);
            }, READY_DELAY_MS);
        }
    }

    /**
     * Encode nested data the way the controllers read it (request params with bracket keys)
     */
    function appendParams(params, value, key) {
        if (value === null || value === undefined) {
            return;
        }
        if (Array.isArray(value)) {
            value.forEach(function (item, index) {
                appendParams(params, item, key + '[' + index + ']');
            });
            return;
        }
        if (typeof value === 'object') {
            Object.keys(value).forEach(function (childKey) {
                appendParams(params, value[childKey], key ? key + '[' + childKey + ']' : childKey);
            });
            return;
        }
        params.append(key, String(value));
    }

    function money(value) {
        var amount = parseFloat(value);
        return (isNaN(amount) ? 0 : amount).toFixed(2);
    }

    function ApplePayExpress(container, config) {
        this.container = container;
        this.button = container.querySelector('apple-pay-button');
        this.config = config;
        this.page = config.page;
        this.shippingGroups = {};
        this.selectedShippingMethod = null;
        this.productSelected = {};
        this.totals = {};
        this.busy = false;
        this.payment = null;
    }

    ApplePayExpress.prototype.url = function (path) {
        return String(this.config.baseUrl || '/').replace(/\/?$/, '/') + path;
    };

    ApplePayExpress.prototype.post = function (path, data) {
        var params = new URLSearchParams();
        appendParams(params, data, '');
        params.append('form_key', this.config.formKey || '');

        return fetch(this.url(path), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    };

    ApplePayExpress.prototype.isSupported = function () {
        if (!window.ApplePaySession) {
            return Promise.resolve(false);
        }
        if (typeof window.ApplePaySession.applePayCapabilities === 'function') {
            return window.ApplePaySession.applePayCapabilities(this.config.guid)
                .then(function (capabilities) {
                    return capabilities.paymentCredentialStatus !== 'applePayUnsupported';
                })
                .catch(function () {
                    return false;
                });
        }
        return Promise.resolve(!!window.ApplePaySession.canMakePayments());
    };

    ApplePayExpress.prototype.init = function () {
        var self = this;

        this.position();
        this.isSupported().then(function (supported) {
            if (!supported) {
                return;
            }

            self.button.setAttribute('buttonstyle', self.config.buttonStyle || 'black');
            if (self.config.cultureCode) {
                self.button.setAttribute('locale', self.config.cultureCode);
            }
            self.button.addEventListener('click', function (event) {
                event.stopPropagation();
                self.start(event);
            });
            self.container.style.display = '';
        });
    };

    ApplePayExpress.prototype.position = function () {
        if (this.page === 'product') {
            var container = this.container;
            var addToCart = document.getElementById('product-addtocart-button');
            var row = addToCart && addToCart.closest('.flex.flex-col.sm\\:flex-row.items-end.my-4');
            var paypalPlaced = function () {
                var paypal = document.getElementById('paypal-express-button-component-hyva');
                return !paypal || paypal.previousElementSibling === row;
            };

            // The PayPal Express script moves its button below Add to Cart; place Apple Pay after it
            whenReady(paypalPlaced, function () {
                var anchor = document.getElementById('paypal-express-button-component-hyva') || row;
                if (anchor && anchor.parentNode && anchor.nextSibling !== container) {
                    anchor.parentNode.insertBefore(container, anchor.nextSibling);
                }
            });
            return;
        }

        var checkoutButton = document.getElementById('checkout-link-button');
        var checkoutItem = checkoutButton && checkoutButton.closest('li.item');
        if (!checkoutItem || !checkoutItem.parentNode) {
            return;
        }
        var paypal = document.getElementById('paypal-express-cart-hyva');
        var after = (paypal && paypal.closest('li.item')) || checkoutItem;
        var item = document.createElement('li');
        item.className = 'item';
        after.parentNode.insertBefore(item, after.nextSibling);
        item.appendChild(this.container);
    };

    /**
     * Product page: the add-to-cart form must be complete before the sheet opens
     */
    ApplePayExpress.prototype.readProduct = function () {
        var form = document.getElementById('product_addtocart_form');
        if (!form) {
            this.showError('Unable to find the product form.');
            return false;
        }
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            form.reportValidity();
            return false;
        }

        var data = new FormData(form);
        var options = {};
        data.forEach(function (value, key) {
            var match = /^super_attribute\[(\d+)\]$/.exec(key);
            if (match && value !== '') {
                options[match[1]] = value;
            }
        });

        var productId = data.get('product');
        var qty = parseFloat(data.get('qty') || '1');
        if (!productId || !(qty > 0)) {
            this.showError('Please check the product and quantity.');
            return false;
        }

        this.productSelected = { id: productId, qty: qty, selected_options: options };
        return true;
    };

    ApplePayExpress.prototype.lineItems = function (type) {
        var totals = this.totals;
        var items = [
            { label: 'Subtotal', amount: money(totals.subtotal), type: type },
            { label: 'Delivery costs', amount: money(totals.shipping), type: type }
        ];
        if (parseFloat(totals.discount) < 0) {
            items.push({ label: 'Discount', amount: money(totals.discount), type: type });
        }
        return items;
    };

    ApplePayExpress.prototype.totalLine = function (type) {
        return { label: this.config.storeName, amount: money(this.totals.grand_total), type: type };
    };

    ApplePayExpress.prototype.shippingMethods = function () {
        var groups = this.shippingGroups;
        return Object.keys(groups).map(function (code) {
            var rate = groups[code];
            return {
                label: rate.carrier_title,
                amount: money(rate.price_incl_tax),
                identifier: rate.method_code,
                detail: rate.method_title
            };
        });
    };

    ApplePayExpress.prototype.start = function (event) {
        if (this.busy) {
            return;
        }
        if (this.page === 'product' && !this.readProduct()) {
            return;
        }

        var qty = this.page === 'product' ? this.productSelected.qty : 1;
        var pending = money((parseFloat(this.config.amount) || 0) * qty);
        this.totals = { subtotal: pending, shipping: 0, grand_total: pending };
        this.shippingGroups = {};
        this.selectedShippingMethod = null;

        var options = new window.BuckarooSdk.ApplePay.ApplePayOptions(
            this.config.storeName,
            this.config.country,
            this.config.currency,
            this.config.cultureCode,
            this.config.guid,
            this.lineItems('pending'),
            this.totalLine('pending'),
            'shipping',
            [],
            this.captureFunds.bind(this),
            this.onShippingMethod.bind(this),
            this.onShippingContact.bind(this),
            ['postalAddress', 'name', 'phone', 'email'],
            ['postalAddress', 'name', 'phone', 'email'],
            this.onCancel.bind(this),
            undefined,
            this.config.supportedNetworks
        );

        this.busy = true;
        // Created and started inside the click: Apple Pay requires a user gesture
        this.payment = new window.BuckarooSdk.ApplePay.ApplePayPayment(this.button, options);
        this.payment.beginPayment(event);
    };

    ApplePayExpress.prototype.onCancel = function () {
        this.busy = false;
    };

    ApplePayExpress.prototype.applyShippingResult = function (result) {
        var self = this;
        this.shippingGroups = {};
        (result.data.shipping_methods || []).forEach(function (rate) {
            self.shippingGroups[rate.method_code] = rate;
        });
        var first = Object.keys(this.shippingGroups)[0];
        if (first && this.selectedShippingMethod === null) {
            var rate = this.shippingGroups[first];
            this.selectedShippingMethod = {
                identifier: rate.method_code,
                label: rate.carrier_title,
                detail: rate.method_title,
                amount: money(rate.price_incl_tax)
            };
        }
        this.totals = result.data.totals || this.totals;
    };

    ApplePayExpress.prototype.failedUpdate = function (message) {
        var errors = [];
        if (typeof window.ApplePayError === 'function') {
            errors.push(new window.ApplePayError('addressUnserviceable', undefined, message || ''));
        }
        return {
            errors: errors,
            newShippingMethods: [],
            newTotal: this.totalLine('final'),
            newLineItems: this.lineItems('final')
        };
    };

    ApplePayExpress.prototype.onShippingContact = function (contact) {
        var self = this;
        var request = this.page === 'product'
            ? this.post('buckaroo/applepay/add', { product: this.productSelected, wallet: contact })
            : this.post('buckaroo/applepay/getShippingMethods', { wallet: contact });

        return request.then(function (result) {
            if (!result || result.success !== true) {
                return self.failedUpdate(result && result.error);
            }
            self.applyShippingResult(result);
            return {
                errors: [],
                newShippingMethods: self.shippingMethods(),
                newTotal: self.totalLine('final'),
                newLineItems: self.lineItems('final')
            };
        }).catch(function () {
            return self.failedUpdate();
        });
    };

    ApplePayExpress.prototype.onShippingMethod = function (method) {
        var self = this;
        this.selectedShippingMethod = method;

        return this.post('buckaroo/applepay/updateShippingMethods', { wallet: { identifier: method.identifier } })
            .then(function (result) {
                if (result && result.success === true && result.data && result.data.totals) {
                    self.totals = result.data.totals;
                }
                return {
                    newTotal: self.totalLine('final'),
                    newLineItems: self.lineItems('final')
                };
            })
            .catch(function () {
                return {
                    newTotal: self.totalLine('final'),
                    newLineItems: self.lineItems('final')
                };
            });
    };

    ApplePayExpress.prototype.formatTransaction = function (payment) {
        var paymentData = payment.token.paymentData;
        return JSON.stringify({
            paymentData: {
                version: paymentData.version,
                data: paymentData.data,
                signature: paymentData.signature,
                header: {
                    ephemeralPublicKey: paymentData.header.ephemeralPublicKey,
                    publicKeyHash: paymentData.header.publicKeyHash,
                    transactionId: paymentData.header.transactionId
                }
            }
        });
    };

    ApplePayExpress.prototype.captureFunds = function (payment) {
        var self = this;
        var failure = { status: window.ApplePaySession.STATUS_FAILURE, errors: [] };
        var network = payment && payment.token && payment.token.paymentMethod
            ? String(payment.token.paymentMethod.network || '')
            : '';

        var extra = {
            method: 'buckaroo_magento2_applepay',
            po_number: null,
            shippingMethod: this.selectedShippingMethod,
            additional_data: {
                applepayTransaction: this.formatTransaction(payment),
                applepayCardNetwork: network,
                billingContact: payment.billingContact ? JSON.stringify(payment.billingContact) : ''
            }
        };

        return this.post('buckaroo/applepay/saveOrder', { payment: payment, extra: extra })
            .then(function (result) {
                self.busy = false;
                if (result && result.success === true) {
                    var redirect = result.data && result.data.RequiredAction && result.data.RequiredAction.RedirectURL;
                    setTimeout(function () {
                        window.location.href = redirect || self.url('checkout/onepage/success/');
                    }, 1000);
                    return { status: window.ApplePaySession.STATUS_SUCCESS, errors: [] };
                }
                self.showError(result && typeof result.error === 'string' && result.error
                    ? result.error
                    : 'Apple Pay payment failed. Please try again.');
                return failure;
            })
            .catch(function () {
                self.busy = false;
                self.showError('Apple Pay payment failed. Please try again.');
                return failure;
            });
    };

    ApplePayExpress.prototype.showError = function (message) {
        var box = this.container.querySelector('.buckaroo-apple-pay-express-error');
        if (!box) {
            box = document.createElement('div');
            box.className = 'buckaroo-apple-pay-express-error';
            this.container.insertBefore(box, this.container.firstChild);
        }
        box.textContent = message;
        box.style.display = '';
        setTimeout(function () {
            box.style.display = 'none';
        }, 6000);
    };

    window.BuckarooHyvaApplePayExpress = {
        init: function (containerId, config) {
            var container = document.getElementById(containerId);
            if (!container || container.dataset.buckarooApplePayInit) {
                return;
            }
            container.dataset.buckarooApplePayInit = '1';

            whenReady(function () {
                return window.jQuery && window.BuckarooSdk && window.BuckarooSdk.ApplePay && window.ApplePaySession;
            }, function () {
                new ApplePayExpress(container, config).init();
            });
        }
    };
})(window, document);
