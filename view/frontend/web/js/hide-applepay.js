document.addEventListener('DOMContentLoaded', function () {
    const hideApplePayIfUnsupported = function () {
        const checkApplePaySupport = function () {
            if (!('ApplePaySession' in window)) return false;
            if (typeof ApplePaySession === 'undefined') return false;
            return ApplePaySession.canMakePaymentsWithActiveCard(window.merchantIdentifier);
        };

        const applePaySupported = checkApplePaySupport();
        const applePayContainer = document.getElementById('payment-method-option-buckaroo_magento2_applepay');
        if (!applePaySupported && applePayContainer) {
            applePayContainer.style.display = 'none';
        }
    };

    hideApplePayIfUnsupported();

    const observer = new MutationObserver(() => {
        hideApplePayIfUnsupported();
    });

    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    } else {
        console.error("document.body is not available for observation.");
    }
});
