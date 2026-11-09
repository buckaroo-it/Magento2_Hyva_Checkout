/**
 * iDEAL Fast Checkout button for the Hyvä product and cart page.
 *
 * Same flow as the Magento 2 checkout (Buckaroo_Magento2/js/view/checkout/ideal-fast-checkout/pay):
 * create the quote, place the order with iDEAL Fast Checkout and send the shopper to iDEAL,
 * which returns the address and contact details to Buckaroo.
 */
(function (window, document) {
    'use strict';

    var READY_ATTEMPTS = 100;
    var READY_DELAY_MS = 100;
    var isLoggedIn = null;

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

    function customerFromSection(customer) {
        return !!(customer && (customer.firstname || customer.fullname));
    }

    // The page can come from the full page cache, so the login state is read from the private content
    window.addEventListener('private-content-loaded', function (event) {
        var data = event.detail && event.detail.data;
        isLoggedIn = customerFromSection(data && data.customer);
    });

    function loggedIn() {
        if (isLoggedIn !== null) {
            return isLoggedIn;
        }
        try {
            var stored = JSON.parse(window.localStorage.getItem('mage-cache-storage') || '{}');
            return customerFromSection(stored.customer);
        } catch (e) {
            return false;
        }
    }

    function IdealFastCheckout(container, config) {
        this.container = container;
        this.button = container.querySelector('#fast-checkout-ideal-btn');
        this.config = config;
        this.page = config.page;
        this.busy = false;
    }

    IdealFastCheckout.prototype.post = function (path, body) {
        return fetch(this.config.restUrl + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                if (!response.ok) {
                    var error = new Error((data && data.message) || 'Request failed');
                    error.fromServer = !!(data && data.message);
                    throw error;
                }
                return data;
            });
        });
    };

    IdealFastCheckout.prototype.init = function () {
        var self = this;

        this.position();
        this.button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            self.start();
        });
    };

    IdealFastCheckout.prototype.position = function () {
        var container = this.container;

        if (this.page === 'product') {
            var addToCart = document.getElementById('product-addtocart-button');
            var row = addToCart && addToCart.closest('.flex.flex-col.sm\\:flex-row.items-end.my-4');
            var othersPlaced = function () {
                // The PayPal and Apple Pay scripts move their buttons below Add to Cart first
                var paypal = document.getElementById('paypal-express-button-component-hyva');
                var applePay = document.getElementById('apple-pay-express-product-hyva');
                var paypalPlaced = !paypal || paypal.previousElementSibling === row;
                var applePayPlaced = !applePay || applePay.previousElementSibling === (paypal || row);
                return paypalPlaced && applePayPlaced;
            };

            // Below Add to Cart and the other express buttons (PayPal, Apple Pay)
            whenReady(othersPlaced, function () {
                var anchor = document.getElementById('apple-pay-express-product-hyva')
                    || document.getElementById('paypal-express-button-component-hyva')
                    || row;
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
        var previous = document.getElementById('apple-pay-express-cart-hyva')
            || document.getElementById('paypal-express-cart-hyva');
        var after = (previous && previous.closest('li.item')) || checkoutItem;
        var item = document.createElement('li');
        item.className = 'item';
        after.parentNode.insertBefore(item, after.nextSibling);
        item.appendChild(container);
    };

    /**
     * Product page: the add-to-cart form must be complete before the order is started
     */
    IdealFastCheckout.prototype.readProductForm = function () {
        var form = document.getElementById('product_addtocart_form');
        if (!form) {
            this.showError('Unable to find the product form.');
            return null;
        }
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            form.reportValidity();
            return null;
        }

        var data = new FormData(form);
        var qty = parseFloat(data.get('qty') || '1');
        if (!data.get('product') || !(qty > 0)) {
            this.showError('Please check the product and quantity.');
            return null;
        }

        data.delete('form_key');
        return new URLSearchParams(data).toString();
    };

    IdealFastCheckout.prototype.start = function () {
        var self = this;
        var orderData = null;

        if (this.busy) {
            return;
        }
        if (this.page === 'product') {
            orderData = this.readProductForm();
            if (orderData === null) {
                return;
            }
        }

        this.setBusy(true);

        var quote = { page: this.page };
        if (orderData !== null) {
            quote.orderData = orderData;
        }

        this.post('buckaroo/ideal/quote/create', quote)
            .then(function (result) {
                if (!result || !result.cart_id) {
                    throw new Error('Unable to create quote.');
                }
                return self.placeOrder(result.cart_id);
            })
            .then(function (response) {
                self.redirect(response);
            })
            .catch(function (error) {
                self.setBusy(false);
                self.showError(error && error.fromServer ? error.message : 'Cannot create payment');
            });
    };

    IdealFastCheckout.prototype.placeOrder = function (cartId) {
        var paymentMethod = {
            method: 'buckaroo_magento2_ideal',
            additional_data: { issuer: 'fastcheckout' }
        };

        if (loggedIn()) {
            return this.post('buckaroo/payment-information', {
                cartId: cartId,
                paymentMethod: paymentMethod
            });
        }

        // iDEAL returns the shopper's e-mail address; the placeholder is replaced after payment
        return this.post('guest-buckaroo/' + encodeURIComponent(cartId) + '/payment-information', {
            cartId: cartId,
            email: 'guest@example.com',
            paymentMethod: paymentMethod
        });
    };

    IdealFastCheckout.prototype.redirect = function (response) {
        var data = response;
        if (typeof response === 'string') {
            try {
                data = JSON.parse(response);
            } catch (e) {
                data = null;
            }
        }

        var url = data && data.RequiredAction && data.RequiredAction.RedirectURL;
        window.location.assign(typeof url === 'string' && url !== '' ? url : this.config.successUrl);
    };

    IdealFastCheckout.prototype.setBusy = function (busy) {
        this.busy = busy;
        this.button.disabled = busy;
        this.button.setAttribute('aria-busy', busy ? 'true' : 'false');
        this.container.classList.toggle('opacity-50', busy);
    };

    IdealFastCheckout.prototype.showError = function (message) {
        var box = this.container.querySelector('.buckaroo-ideal-fast-checkout-error');
        if (!box) {
            box = document.createElement('div');
            box.className = 'buckaroo-ideal-fast-checkout-error';
            box.setAttribute('role', 'alert');
            this.container.insertBefore(box, this.container.firstChild);
        }
        box.textContent = message;
        box.style.display = '';
        setTimeout(function () {
            box.style.display = 'none';
        }, 6000);
    };

    window.BuckarooHyvaIdealFastCheckout = {
        init: function (containerId, config) {
            var container = document.getElementById(containerId);
            if (!container || container.dataset.buckarooIdealInit) {
                return;
            }
            container.dataset.buckarooIdealInit = '1';
            new IdealFastCheckout(container, config).init();
        }
    };
})(window, document);
