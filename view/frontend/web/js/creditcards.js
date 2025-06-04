/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

// Hosted Fields SDK Integration for Hyva Checkout
window.buckarooHostedFields = {
    sdkClient: null,
    tokenExpiresAt: null,
    oauthTokenError: '',
    paymentError: '',
    isPayButtonDisabled: false,
    service: null,
    encryptedCardData: null,

    /**
     * Retrieve OAuth token via AJAX.
     */
    async getOAuthToken() {
        try {
            const response = await fetch('/buckaroo/credentialschecker/gettoken', {
                method: 'GET',
                headers: {
                    'X-Requested-From': 'MagentoFrontend'
                }
            });
            
            const data = await response.json();
            
            if (data.error) {
                this.oauthTokenError = "An error occurred, please try another payment method or try again later.";
                return false;
            } else {
                const accessToken = data.data.access_token;
                const issuers = data.data.issuers;
                const expiresIn = data.data.expires_in; // lifetime in seconds

                this.tokenExpiresAt = Date.now() + expiresIn * 1000;
                this.scheduleTokenRefresh(expiresIn);
                await this.initHostedFields(accessToken, issuers);
                return true;
            }
        } catch (error) {
            console.error('OAuth token error:', error);
            this.oauthTokenError = "An error occurred, please try another payment method or try again later.";
            return false;
        }
    },

    /**
     * Schedule token refresh before expiry.
     */
    scheduleTokenRefresh(expiresIn) {
        const refreshTime = Math.max(expiresIn * 1000 - 1000, 0);
        setTimeout(() => {
            this.resetHostedFields("We are refreshing the payment form, because the session has expired.");
        }, refreshTime);
    },

    /**
     * Remove hosted field iframes from the DOM.
     */
    removeHostedFieldIframes() {
        const wrappers = ['#cc-name-wrapper', '#cc-number-wrapper', '#cc-expiry-wrapper', '#cc-cvc-wrapper'];
        wrappers.forEach(wrapper => {
            const element = document.querySelector(wrapper);
            if (element) {
                const iframes = element.querySelectorAll('iframe');
                iframes.forEach(iframe => iframe.remove());
            }
        });
    },

    /**
     * Unified function to reset hosted fields.
     * @param {String} [errorMsg] Optional error message to display.
     */
    async resetHostedFields(errorMsg = '') {
        this.removeHostedFieldIframes();
        this.paymentError = errorMsg;
        await this.getOAuthToken();
        this.isPayButtonDisabled = false;
        
        // Trigger update for Alpine.js components
        window.dispatchEvent(new CustomEvent('buckaroo-hosted-fields-reset'));
    },

    /**
     * Initialize hosted fields using the OAuth token and issuers.
     */
    async initHostedFields(accessToken, issuers) {
        try {
            // Load the SDK if not already loaded
            if (!window.BuckarooHostedFieldsSdk) {
                await this.loadHostedFieldsSDK();
            }

            this.sdkClient = new BuckarooHostedFieldsSdk.HFClient(accessToken);
            const locale = document.documentElement.lang;
            const languageCode = locale.split('_')[0];
            this.sdkClient.setLanguage(languageCode);
            this.sdkClient.setSupportedServices(issuers);

            // Start the session and update the pay button state based on validation.
            await this.sdkClient.startSession((event) => {
                this.sdkClient.handleValidation(
                    event,
                    'cc-name-error',
                    'cc-number-error',
                    'cc-expiry-error',
                    'cc-cvc-error'
                );
                this.isPayButtonDisabled = !this.sdkClient.formIsValid();
                this.service = this.sdkClient.getService();
                
                // Trigger update for Alpine.js components
                window.dispatchEvent(new CustomEvent('buckaroo-hosted-fields-validation', {
                    detail: { 
                        isValid: this.sdkClient.formIsValid(),
                        service: this.service 
                    }
                }));
            });

            // Styling for hosted fields.
            const cardLogoStyling = {
                height: "80%",
                position: 'absolute',
                border: '1px solid #d6d6d6',
                borderRadius: "4px",
                opacity: '1',
                transition: 'all 0.3s ease',
                right: '5px',
                backgroundColor: 'inherit'
            };

            const styling = {
                fontSize: "14px",
                fontStyle: "normal",
                fontWeight: 400,
                fontFamily: 'Open Sans, Helvetica Neue, Helvetica, Arial, sans-serif',
                textAlign: 'left',
                background: '#fefefe',
                color: '#333333',
                placeholderColor: '#888888',
                borderRadius: '5px',
                padding: '8px 10px',
                boxShadow: 'none',
                transition: 'border-color 0.2s ease, box-shadow 0.2s ease',
                border: '1px solid #d6d6d6',
                cardLogoStyling: cardLogoStyling
            };

            // Mount hosted fields concurrently.
            const mountCardHolderNamePromise = this.sdkClient.mountCardHolderName("#cc-name-wrapper", {
                id: "ccname",
                placeHolder: "John Doe",
                labelSelector: "#cc-name-label",
                baseStyling: styling
            }).then(field => {
                field.focus();
                return field;
            });

            const mountCardNumberPromise = this.sdkClient.mountCardNumber("#cc-number-wrapper", {
                id: "cc",
                placeHolder: "555x xxxx xxxx xxxx",
                labelSelector: "#cc-number-label",
                baseStyling: styling
            });

            const mountCvcPromise = this.sdkClient.mountCvc("#cc-cvc-wrapper", {
                id: "cvc",
                placeHolder: "1234",
                labelSelector: "#cc-cvc-label",
                baseStyling: styling
            });

            const mountExpiryPromise = this.sdkClient.mountExpiryDate("#cc-expiry-wrapper", {
                id: "expiry",
                placeHolder: "MM / YY",
                labelSelector: "#cc-expiry-label",
                baseStyling: styling
            });

            await Promise.all([
                mountCardHolderNamePromise,
                mountCardNumberPromise,
                mountCvcPromise,
                mountExpiryPromise
            ]);

            // Trigger success event
            window.dispatchEvent(new CustomEvent('buckaroo-hosted-fields-ready'));
            
        } catch (error) {
            console.error("Error initializing hosted fields:", error);
            this.paymentError = "Failed to initialize payment form. Please try again.";
        }
    },

    /**
     * Load the Hosted Fields SDK
     */
    loadHostedFieldsSDK() {
        return new Promise((resolve, reject) => {
            if (window.BuckarooHostedFieldsSdk) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://static.buckaroo.nl/script/HostedFieldsSDK.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    },

    /**
     * Process payment with hosted fields
     */
    async processPayment() {
        this.isPayButtonDisabled = true;

        // Check if the token has expired before processing payment.
        if (Date.now() > this.tokenExpiresAt) {
            await this.resetHostedFields("We are refreshing the payment form, because the session has expired.");
            this.paymentError = "Session expired, please try again.";
            this.isPayButtonDisabled = false;
            return null;
        }

        try {
            const paymentToken = await this.sdkClient.submitSession();
            if (!paymentToken) {
                throw new Error("Failed to get encrypted card data.");
            }
            this.encryptedCardData = paymentToken;
            this.service = this.sdkClient.getService();
            
            return {
                encryptedCardData: this.encryptedCardData,
                service: this.service
            };
        } catch (error) {
            console.error('Payment processing error:', error);
            this.paymentError = "Payment processing failed. Please try again.";
            this.isPayButtonDisabled = false;
            return null;
        }
    }
}; 