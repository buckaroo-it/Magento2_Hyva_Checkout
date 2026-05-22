/**
 * DOM placement helpers for Hyvä PayPal Express buttons.
 */
(function (window) {
    'use strict';

    var resizeListenerAttached = false;
    var resizeTimeout;

    function positionCartPaypalButton() {
        var checkoutButton = document.getElementById('checkout-link-button');
        var paypalContainer = document.getElementById('paypal-express-cart-hyva');

        if (!checkoutButton || !paypalContainer) {
            return;
        }

        var checkoutListItem = checkoutButton.closest('li.item');
        if (!checkoutListItem || !checkoutListItem.parentElement) {
            return;
        }

        var paypalListItem = paypalContainer.closest('li.item');

        if (paypalListItem && checkoutListItem.nextSibling === paypalListItem) {
            matchCartButtonWidth(checkoutButton, paypalContainer);
            return;
        }

        if (!paypalListItem) {
            paypalListItem = document.createElement('li');
            paypalListItem.className = 'item';
            paypalContainer.parentNode.insertBefore(paypalListItem, paypalContainer);
            paypalListItem.appendChild(paypalContainer);
        }

        var parent = checkoutListItem.parentNode;
        if (checkoutListItem.nextSibling) {
            parent.insertBefore(paypalListItem, checkoutListItem.nextSibling);
        } else {
            parent.appendChild(paypalListItem);
        }

        matchCartButtonWidth(checkoutButton, paypalContainer);

        if (!resizeListenerAttached) {
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function () {
                    matchCartButtonWidth(checkoutButton, paypalContainer);
                }, 100);
            });
            resizeListenerAttached = true;
        }
    }

    function matchCartButtonWidth(checkoutButton, paypalContainer) {
        var checkoutButtonWidth = checkoutButton.offsetWidth;
        var computedStyle = window.getComputedStyle(checkoutButton);

        paypalContainer.style.width = checkoutButtonWidth + 'px';
        paypalContainer.style.maxWidth = computedStyle.maxWidth || 'none';
        paypalContainer.style.minWidth = computedStyle.minWidth || 'auto';
    }

    function positionProductPaypalButton() {
        var addToCartButton = document.getElementById('product-addtocart-button');
        var paypalContainer = document.getElementById('paypal-express-button-component-hyva');

        if (!addToCartButton || !paypalContainer) {
            return;
        }

        var outerContainer = addToCartButton.closest('.flex.flex-col.sm\\:flex-row.items-end.my-4');
        if (!outerContainer || !outerContainer.parentElement) {
            return;
        }

        if (outerContainer.nextSibling === paypalContainer) {
            return;
        }

        outerContainer.parentNode.insertBefore(paypalContainer, outerContainer.nextSibling || null);
    }

    window.BuckarooHyvaPaypalExpress = window.BuckarooHyvaPaypalExpress || {};
    window.BuckarooHyvaPaypalExpress.positionCartPaypalButton = positionCartPaypalButton;
    window.BuckarooHyvaPaypalExpress.positionProductPaypalButton = positionProductPaypalButton;
})(window);
