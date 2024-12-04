document.addEventListener('DOMContentLoaded', function() {
    const checkPaySupport = function () {
        if (!('ApplePaySession' in window)) return false;
        if (typeof ApplePaySession === 'undefined') return false;
        return true;
    };

    const applePaySupported = checkPaySupport();
    const applePayContainer = document.getElementById('payment-method-option-buckaroo_magento2_applepay');
    if (!applePaySupported && applePayContainer) {
        applePayContainer.style.display = 'none';
    }
});
