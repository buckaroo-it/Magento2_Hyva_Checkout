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

// Buckaroo Alpine.js Components for Hyva Checkout
window.buckaroo = window.buckaroo || {};

function initializeBuckarooComponents() {
    window.buckaroo = {
        start() {
            if (typeof hyvaCheckout === 'undefined' || typeof hyva === 'undefined') {
                setTimeout(() => this.start(), 100);
                return;
            }

            const addTask = function() {
                hyvaCheckout.navigation.addTask(async () => {
                    if (window.buckarooTask) {
                        await window.buckarooTask();
                    }
                });
            };
            addTask();
            window.addEventListener('checkout:navigation:success', (event) => {
                if (event.detail.route === 'payment') {
                    addTask();
                }
            });

            function buckaroo_load_sdk() {
                return new Promise(resolve => {
                    const script = document.createElement('script');
                    script.type = 'text/javascript';
                    script.src = '//static.buckaroo.nl/script/ClientSideEncryption001.js';
                    script.async = true;
                    script.onload = resolve;
                    document.head.appendChild(script);
                })
            }

            buckaroo_load_sdk().then(() => {
                window.buckarooCseHasLoaded = true;
                hyva.formValidation.addRule('bk-validateCardNumber', (value) => {
                    if (!BuckarooClientSideEncryption.V001.validateCardNumber(value.replace(/\s+/g, ''))) {
                        return 'Please enter a valid creditcard number.';
                    };
                    return true;
                });

                hyva.formValidation.addRule('bk-validateCardCvc', (value, options, field, context) => {
                    if (!BuckarooClientSideEncryption.V001.validateCvc(
                            value,
                            context.determineIssuer(context.cardNumber)
                        )) {
                        return 'Please enter a valid Cvc number.';
                    };
                    return true;
                });

                hyva.formValidation.addRule('bk-validateCardHolderName', (value) => {
                    if (!BuckarooClientSideEncryption.V001.validateCardholderName(value)) {
                        return 'Please enter a valid card holder name.';
                    };
                    return true;
                });

                hyva.formValidation.addRule('bk-ValidateYear', (value) => {
                    const message = 'Enter a valid year number.';

                    if (value.length === 0) {
                        return message;
                    }
                    const parts = value.split("/");
                    if (!BuckarooClientSideEncryption.V001.validateYear(parts[1])) {
                        return message;
                    };
                    return true;
                });

                hyva.formValidation.addRule('bk-ValidateMonth', (value) => {
                    const message = 'Enter a valid month number.';
                    if (value.length === 0) {
                        return message;
                    }

                    const parts = value.split("/");
                    if (!BuckarooClientSideEncryption.V001.validateMonth(parts[0])) {
                        return message;
                    };
                    return true;
                });
                window.dispatchEvent(new CustomEvent("buckaroo-cse-load"));
            })

            if (typeof Magewire !== 'undefined') {
                Magewire.hook('element.removed', (el, component) => {
                    if (el.id !== undefined && el.id.indexOf('payment-method-view-buckaroo_magento2') > -1) {
                        window.buckarooTask = undefined;
                    }
                })
            }
        },

        // Shared notification system
        showTemporaryBanner(message, type = 'success') {
            const banner = document.createElement('div');
            const isSuccess = type === 'success';
            
            banner.className = 'buckaroo-notification';
            banner.setAttribute('data-type', type);
            
            banner.innerHTML = `
                <div class="buckaroo-notification-content">
                    <div class="buckaroo-notification-message">
                        <svg class="buckaroo-notification-icon" viewBox="0 0 20 20">
                            ${isSuccess 
                                ? '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>'
                                : '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>'
                            }
                        </svg>
                        <span>${message}</span>
                    </div>
                    <button class="buckaroo-notification-close" onclick="this.closest('.buckaroo-notification').remove()">×</button>
                </div>
            `;
            
            document.body.appendChild(banner);
            
            // Animate in
            setTimeout(() => {
                banner.classList.add('buckaroo-notification-show');
            }, 10);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (banner.parentElement) {
                    banner.classList.remove('buckaroo-notification-show');
                    setTimeout(() => banner.remove(), 300);
                }
            }, 5000);
        },

        applePay(jsSdk) {
            return {
                config: {},
                canDisplay: false,
                loadSdk() {
                    return new Promise(resolve => {
                        const script = document.createElement('script');
                        script.type = 'text/javascript';
                        script.src = jsSdk;
                        script.async = true;
                        script.onload = resolve;
                        document.head.appendChild(script);
                    })
                },

                async formatTransactionResponse(response) {
                    if (response === null || response === 'undefined') {
                        return null;
                    }

                    const paymentData = response.token.paymentData;

                    return JSON.stringify({
                        paymentData: {
                            version: paymentData.version,
                            data: paymentData.data,
                            signature: paymentData.signature,
                            header: {
                                ephemeralPublicKey: paymentData.header.ephemeralPublicKey,
                                publicKeyHash: paymentData.header.publicKeyHash,
                                transactionId: paymentData.header.transactionId,
                            },
                        },
                    });
                },

                async captureFunds(payment) {
                    const billingContact = payment && payment.billingContact
                        ? JSON.stringify(payment.billingContact)
                        : '';

                    const formattedData = await this.formatTransactionResponse(payment);
                    await this.$wire.updateData(formattedData, billingContact);
                    this.resolve();

                    return {
                        status: window.ApplePaySession.STATUS_SUCCESS,
                        errors: [],
                    };
                },

                async beginPayment() {
                    const promise = new Promise((resolve) => {
                        this.resolve = resolve;

                        const config = new BuckarooApplePay.PayOptions(
                            this.config.storeName,
                            this.config.country,
                            this.config.currency,
                            this.config.cultureCode,
                            this.config.guid,
                            this.$wire.get('totals'),
                            this.$wire.get('grandTotal'),
                            'shipping',
                            [],
                            (payment) => this.captureFunds(payment),
                            null,
                            null,
                            ["name", "postalAddress", "phone"],
                            ["name", "postalAddress", "phone"]
                        );
                        new BuckarooApplePay.PayPayment(config).beginPayment()
                    })
                    await promise;
                },

                async submit() {
                    await hyvaCheckout.order.place();
                },

                async register() {
                    this.config = this.$wire.get('config');
                    window.merchantIdentifier = this.config.guid;
                    this.loadSdk().then(async () => {
                        this.canDisplay = await BuckarooApplePay.checkPaySupport(this.config.guid);
                        window.buckarooTask = async () => {
                            if(this.canDisplay)
                                await this.beginPayment();
                        };
                    });
                }
            }
        },

        credicards($el) {
            return Object.assign((typeof hyva !== 'undefined' ? hyva.formValidation($el) : {}), {
                oauthTokenError: '',
                paymentError: '',
                isPayButtonDisabled: false,
                hostedFieldsReady: false,

                async initCreditCardFields() {
                    if (window.buckarooHostedFields) {
                        await window.buckarooHostedFields.getOAuthToken();
                    }
                },

                async resetHostedFields() {
                    if (window.buckarooHostedFields) {
                        await window.buckarooHostedFields.resetHostedFields();
                    }
                },

                register() {
                    window.addEventListener('buckaroo-hosted-fields-ready', () => {
                        this.hostedFieldsReady = true;
                        this.oauthTokenError = '';
                        this.paymentError = '';
                    });

                    window.addEventListener('buckaroo-hosted-fields-validation', (event) => {
                        this.isPayButtonDisabled = !event.detail.isValid;
                    });

                    window.addEventListener('buckaroo-hosted-fields-reset', () => {
                        this.oauthTokenError = window.buckarooHostedFields.oauthTokenError;
                        this.paymentError = window.buckarooHostedFields.paymentError;
                        this.isPayButtonDisabled = window.buckarooHostedFields.isPayButtonDisabled;
                    });

                    window.buckarooTask = async () => {
                        if (this.hostedFieldsReady && !this.isPayButtonDisabled) {
                            const paymentData = await window.buckarooHostedFields.processPayment();
                            if (paymentData) {
                                await this.$wire.updatedEncryptedData(
                                    paymentData.encryptedCardData,
                                    paymentData.service
                                );
                            } else {
                                this.oauthTokenError = window.buckarooHostedFields.oauthTokenError;
                                this.paymentError = window.buckarooHostedFields.paymentError;
                                this.isPayButtonDisabled = window.buckarooHostedFields.isPayButtonDisabled;
                            }
                        }
                    };
                }
            })
        },

        giftcards($el) {
            return Object.assign((typeof hyva !== 'undefined' ? hyva.formValidation($el) : {}), {
                card:'',
                pin: '',
                cardNumber: '',
                canSubmit:false,
                
                updateCard(event) {
                    this.card = event.target.value;
                    this.onChange();
                },
                
                updateCardNumber(event) {
                    this.cardNumber = event.target.value;
                    this.onChange();
                },
                
                updatePin(event) {
                    this.pin = event.target.value;
                    this.onChange();
                },
                
                onChange() {
                    // Called by template on change events
                },
                
                async formValid() {
                    try {
                        await this.validate();
                        return true;
                    } catch (error) {
                        return false;
                    }
                },
                
                async submit() {
                    if(!await this.formValid()) {
                        return;
                    }
                    
                    let responseHandled = false;
                    const responsePromise = this.waitForGiftcardResponse(5000);
                    
                    try {
                        if (this.$wire && typeof this.$wire.applyGiftcard === 'function') {
                            const wireCallPromise = Promise.race([
                                this.$wire.applyGiftcard(this.card, this.cardNumber, this.pin),
                                new Promise((_, reject) => {
                                    setTimeout(() => reject(new Error('Wire call timeout')), 3000);
                                })
                            ]);
                            
                            try {
                                await wireCallPromise;
                                try {
                                    await responsePromise;
                                    responseHandled = true;
                                    return;
                                } catch (eventError) {
                                    this.displayGenericSuccess();
                                    responseHandled = true;
                                    return;
                                }
                            } catch (wireError) {
                                console.warn('Giftcard wire call error:', wireError);
                                try {
                                    await Promise.race([
                                        responsePromise,
                                        new Promise((_, reject) => setTimeout(() => reject(new Error('Response timeout')), 2000))
                                    ]);
                                    responseHandled = true;
                                    return;
                                } catch (eventError) {
                                    // Continue to fallback
                                }
                            }
                        }
                        
                        if (!responseHandled) {
                            if (this.$wire && typeof this.$wire.call === 'function') {
                                try {
                                    await this.$wire.call('applyGiftcard', this.card, this.cardNumber, this.pin);
                                    await responsePromise;
                                    responseHandled = true;
                                    return;
                                } catch (callError) {
                                    console.warn('Giftcard call error:', callError);
                                }
                            }
                            
                            if (!responseHandled) {
                                await this.submitViaAjax();
                                responseHandled = true;
                            }
                        }
                        
                    } catch (error) {
                        console.warn('Giftcard submission error:', error);
                        if (!responseHandled) {
                            try {
                                await this.submitViaAjax();
                            } catch (ajaxError) {
                                window.buckaroo.showTemporaryBanner('Unable to process giftcard. Please try again.', 'error');
                            }
                        }
                    }
                },
                
                displayGenericSuccess() {
                    if (typeof hyvaCheckout !== 'undefined' && hyvaCheckout.order && hyvaCheckout.order.refreshTotals) {
                        try {
                            hyvaCheckout.order.refreshTotals();
                        } catch (e) {
                            // Silent fail
                        }
                    }
                    
                    const message = 'Giftcard has been applied successfully! The order total will be updated.';
                    window.buckaroo.showTemporaryBanner(message, 'success');
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                },
                
                async waitForGiftcardResponse(timeout = 3000) {
                    return new Promise((resolve, reject) => {
                        let responseReceived = false;
                        
                        const handleResponse = (response) => {
                            if (responseReceived) return;
                            responseReceived = true;
                            this.handleGiftcardResponse(response);
                            resolve(response);
                        };
                        
                        const eventHandler = (event) => {
                            handleResponse(event.detail);
                        };
                        
                        window.addEventListener('buckaroo-giftcard-response', eventHandler, { once: true });
                        
                        if (this.$wire && this.$wire.on) {
                            this.$wire.on('giftcard_response', handleResponse);
                        }
                        
                        setTimeout(() => {
                            if (!responseReceived) {
                                window.removeEventListener('buckaroo-giftcard-response', eventHandler);
                                reject(new Error('Timeout waiting for giftcard response'));
                            }
                        }, timeout);
                    });
                },
                
                handleGiftcardResponse(response) {
                    if (response.error) {
                        this.displayError(response.error);
                    } else if(response.remainder_amount === undefined) {
                        this.displayError(response.message);
                    } else if (response.remainder_amount == 0) {
                        this.canSubmit = true;
                        this.displaySuccess({
                            ...response,
                            message: 'Giftcard applied successfully. You can now complete your order.'
                        });
                    } else if (response.remainder_amount != 0) {
                        this.displaySuccess(response);
                    }
                },
                
                async submitViaAjax() {
                    const response = await fetch('/buckaroo/checkout/giftcard', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            card: this.card,
                            cardNumber: this.cardNumber,
                            pin: this.pin
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.error) {
                        this.displayError(result.error);
                    } else if(result.remainder_amount === undefined) {
                        this.displayError(result.message);
                    } else if (result.remainder_amount == 0) {
                        this.canSubmit = true;
                        await hyvaCheckout.order.place();
                    } else if (result.remainder_amount != 0) {
                        this.displaySuccess(result);
                    }
                },
                
                displaySuccess(response) {
                    window.buckaroo.showTemporaryBanner(response.message, 'success');
                    
                    // Refresh page to show updated totals after successful giftcard application
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                },
                
                displayError(message) {
                    window.buckaroo.showTemporaryBanner(message, 'error');
                },
                
                listenToSubmit() {
                    if (this.$wire && this.$wire.on) {
                        this.$wire.on('giftcard_response', (response) => {
                            this.handleGiftcardResponse(response);
                        });
                    }
                    
                    window.addEventListener('buckaroo-giftcard-response', (event) => {
                        this.handleGiftcardResponse(event.detail);
                    });
                },
                
                register() {
                    this.listenToSubmit();
                    window.buckarooTask = async () => {
                        if(!this.canSubmit) {
                            await this.submit();
                        }
                    };
                },
            })
        },

        mrCash($el) {
            return Object.assign((typeof hyva !== 'undefined' ? hyva.formValidation($el) : {}), {
                cseHasLoaded: window.buckarooCseHasLoaded,
                cardHolder: '',
                cardNumber: '',
                cardExpiration: '',
                
                updateCardHolder(event) {
                    this.cardHolder = event.target.value;
                    this.onChange();
                },
                
                updateCardNumber(event) {
                    this.cardNumber = event.target.value;
                    this.onChange();
                },
                
                updateCardExpiration(event) {
                    this.cardExpiration = event.target.value;
                    this.onChange();
                },
                
                onChange() {
                    // Called by template on change events
                },
                
                async saveEncryptedData() {
                    let parts = this.cardExpiration.split('/');
                    const year = parts[1];
                    const month = parts[0];
                    const enc = new Promise((resolve) => {
                        BuckarooClientSideEncryption.V001.encryptCardData(
                            this.cardNumber,
                            year,
                            month,
                            '',
                            this.cardHolder,
                            function(encryptedCardData) {
                                resolve(encryptedCardData);
                            });
                    })
                    return await enc;
                },
                
                register() {
                    window.addEventListener('buckaroo-cse-load', () => {
                        this.cseHasLoaded = true;
                    }, {
                        once: true
                    })

                    const formValid = async () => {
                        try {
                            await this.validate();
                            return true;
                        } catch (error) {
                            return false;
                        }
                    }
                    
                    window.buckarooTask = async () => {
                        const isValid = await formValid();
                        if (this.cseHasLoaded && isValid) {
                            const encryptedCardData = await this.saveEncryptedData();
                            await this.$wire.updatedEncryptedData(encryptedCardData);
                        }
                    };
                }
            })
        },

        voucher($el, ajaxUrl) {
            return Object.assign((typeof hyva !== 'undefined' ? hyva.formValidation($el) : {}), {
                code:'',
                canSubmit:false,
                
                updateCode(event) {
                    this.code = event.target.value;
                    this.onChange();
                },
                
                onChange() {
                    // Called by template on change events
                },
                
                async formValid() {
                    try {
                        await this.validate();
                        return true;
                    } catch (error) {
                        return false;
                    }
                },

                async submit() {
                    if(!await this.formValid()) {
                        return;
                    }

                    const params = {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({voucherCode: this.code})
                    };

                    const response = await (
                        await fetch(ajaxUrl, params)
                    ).json();
                    await this.$wire.refreshTotals();
                    
                    if (response.error) {
                        this.displayError(response.error);
                    } else if(response.remainder_amount === undefined) {
                        this.displayError(response.message);
                    } else if (response.remainder_amount == 0) {
                        this.canSubmit = true;
                        await this.$wire.setCanSubmit(true);
                        await hyvaCheckout.order.place();
                    } else if (response.remainder_amount != 0) {
                        this.displaySuccess(response);
                    }
                },

                displaySuccess(response) {
                    window.buckaroo.showTemporaryBanner(response.message, 'success');
                },

                displayError(message) {
                    window.buckaroo.showTemporaryBanner(message, 'error');
                },

                register() {
                    window.buckarooTask = async () => {
                        if(!this.canSubmit) {
                            await this.submit();
                        }
                    };
                },
            })
        }
    };

    // Initialize
    if (window.buckaroo.start) {
        window.buckaroo.start();
    }

    // Register Alpine.data components
    if (typeof Alpine !== 'undefined' && Alpine.data) {
        
        // CSP-friendly date handler for date of birth fields
        Alpine.data('buckarooDateHandler', () => ({
            value: '',
            
            init() {
                // Get initial value from data attribute
                const initialValue = this.$el.getAttribute('data-initial-value');
                this.value = initialValue || '';
            },
            
            handleDateChange(event) {
                if (event && event.target) {
                    this.value = event.target.value;
                    // Use $wire directly instead of storing it
                    if (this.$wire && typeof this.$wire.call === 'function') {
                        this.$wire.call('updatedDateOfBirth', this.value);
                    }
                }
            }
        }));
        
        Alpine.data('buckarooCreditcards', () => {
            return {
                oauthTokenError: '',
                paymentError: '',
                isPayButtonDisabled: false,
                hostedFieldsReady: false,
                init() {
                    if (window.buckaroo && window.buckaroo.credicards) {
                        Object.assign(this, window.buckaroo.credicards(this.$el));
                        this.$wire = this.$wire;
                        if (this.register) this.register();
                        if (this.initCreditCardFields) this.initCreditCardFields();
                    }
                }
            };
        });

        Alpine.data('buckarooGiftcards', () => {
            return {
                card: '',
                pin: '',
                cardNumber: '',
                canSubmit: false,
                init() {
                    if (window.buckaroo && window.buckaroo.giftcards) {
                        Object.assign(this, window.buckaroo.giftcards(this.$el));
                        if (this.register) {
                            this.register();
                        }
                    }
                },
                updateCard(event) {
                    this.card = event.target.value;
                    if (this.onChange) this.onChange();
                },
                updateCardNumber(event) {
                    this.cardNumber = event.target.value;
                    if (this.onChange) this.onChange();
                },
                updatePin(event) {
                    this.pin = event.target.value;
                    if (this.onChange) this.onChange();
                }
            };
        });

        Alpine.data('buckarooVoucher', () => {
            return {
                code: '',
                canSubmit: false,
                init() {
                    if (window.buckaroo && window.buckaroo.voucher) {
                        const ajaxUrl = this.$el.dataset.ajaxUrl || '';
                        Object.assign(this, window.buckaroo.voucher(this.$el, ajaxUrl));
                        this.$wire = this.$wire;
                        if (this.register) this.register();
                    }
                },
                updateCode(event) {
                    this.code = event.target.value;
                    if (this.onChange) this.onChange();
                }
            };
        });

        Alpine.data('buckarooMrCash', () => {
            return {
                cseHasLoaded: false,
                cardHolder: '',
                cardNumber: '',
                cardExpiration: '',
                init() {
                    if (window.buckaroo && window.buckaroo.mrCash) {
                        Object.assign(this, window.buckaroo.mrCash(this.$el));
                        this.$wire = this.$wire;
                        if (this.register) this.register();
                    }
                },
                updateCardHolder(event) {
                    this.cardHolder = event.target.value;
                    if (this.onChange) this.onChange();
                },
                updateCardNumber(event) {
                    this.cardNumber = event.target.value;
                    if (this.onChange) this.onChange();
                },
                updateCardExpiration(event) {
                    this.cardExpiration = event.target.value;
                    if (this.onChange) this.onChange();
                }
            };
        });

        Alpine.data('buckarooApplepay', () => {
            return {
                config: null,
                canDisplay: false,
                isClientSide: false,
                init() {
                    console.log('[Buckaroo Apple Pay] Initializing...');
                    
                    // Read isClientSide from data attribute
                    this.isClientSide = this.$el.dataset.isClientSide === 'true';
                    
                    console.log('[Buckaroo Apple Pay] isClientSide:', this.isClientSide);
                    console.log('[Buckaroo Apple Pay] $wire available:', !!this.$wire);
                    
                    if (!this.isClientSide) {
                        console.log('[Buckaroo Apple Pay] Server-side mode, skipping');
                        return;
                    }
                    
                    if (window.buckaroo && window.buckaroo.applePay) {
                        const jsSdkUrl = this.$el.dataset.jsSdkUrl || '';
                        
                        if (!jsSdkUrl) {
                            console.error('[Buckaroo Apple Pay] SDK URL not found');
                            return;
                        }
                        
                        console.log('[Buckaroo Apple Pay] SDK URL:', jsSdkUrl);
                        console.log('[Buckaroo Apple Pay] Merging Apple Pay instance');
                        
                        Object.assign(this, window.buckaroo.applePay(jsSdkUrl));
                        this.$wire = this.$wire;
                        
                        console.log('[Buckaroo Apple Pay] After merge - $wire:', !!this.$wire);
                        console.log('[Buckaroo Apple Pay] After merge - register function:', typeof this.register);
                        
                        if (this.register && typeof this.register === 'function') {
                            console.log('[Buckaroo Apple Pay] Calling register()');
                            this.register();
                        }
                    } else {
                        console.error('[Buckaroo Apple Pay] window.buckaroo.applePay not available');
                    }
                }
            };
        });
    }
}

// Initialize when Alpine is ready
document.addEventListener('alpine:init', () => {
    initializeBuckarooComponents();
});

// Initialize immediately if Alpine is already loaded
if (typeof Alpine !== 'undefined' && Alpine.version) {
    initializeBuckarooComponents();
}

// Performance optimization: Preload critical resources
if (typeof window !== 'undefined') {
    const preloadBuckarooSDK = () => {
        if (!window.buckarooCseHasLoaded) {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'script';
            link.href = '//static.buckaroo.nl/script/ClientSideEncryption001.js';
            document.head.appendChild(link);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preloadBuckarooSDK);
    } else {
        preloadBuckarooSDK();
    }
}
