document.addEventListener('DOMContentLoaded', function () {
    const CONTAINER_ID = 'payment-method-option-buckaroo_magento2_applepay';
    let supportCheck = null;

    const loadAppleSdk = function () {
        return typeof window.buckarooLoadAppleSdk === 'function'
            ? window.buckarooLoadAppleSdk()
            : Promise.resolve();
    };

    // Same rule as the Luma checkout: Apple Pay is offered unless the browser reports it as unsupported.
    // The Apple Pay JS SDK provides ApplePaySession in browsers other than Safari.
    const checkApplePaySupport = async function () {
        await loadAppleSdk();

        if (!('ApplePaySession' in window) || typeof ApplePaySession === 'undefined') {
            return false;
        }

        const configElement = document.getElementById('buckaroo-applepay-checkout-config');
        const merchantIdentifier = window.merchantIdentifier || (configElement ? configElement.dataset.guid : '');

        try {
            if (merchantIdentifier && typeof ApplePaySession.applePayCapabilities === 'function') {
                const capabilities = await ApplePaySession.applePayCapabilities(merchantIdentifier);
                return capabilities.paymentCredentialStatus !== 'applePayUnsupported';
            }

            return ApplePaySession.canMakePayments();
        } catch (error) {
            return false;
        }
    };

    const hideApplePayIfUnsupported = function () {
        const applePayContainer = document.getElementById(CONTAINER_ID);
        if (!applePayContainer || applePayContainer.dataset.buckarooApplePayChecked) {
            return;
        }

        applePayContainer.dataset.buckarooApplePayChecked = '1';
        supportCheck = supportCheck || checkApplePaySupport();
        supportCheck.then(function (applePaySupported) {
            if (!applePaySupported) {
                applePayContainer.style.display = 'none';
            }
        });
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
