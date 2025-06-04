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

// Immediately define buckaroo object to prevent Alpine.js errors
window.buckaroo = window.buckaroo || {
    modal: () => ({ showModal: false, title: '', content: '', showClose: true, buttons: [], close: () => {}, initModal: () => {} }),
    credicards: () => ({ register: () => {}, initCreditCardFields: () => {}, resetHostedFields: () => {} }),
    giftcards: () => ({ register: () => {} }),
    mrCash: () => ({ register: () => {} }),
    voucher: () => ({ register: () => {} }),
    applePay: () => ({ register: () => {} }),
    start: () => {}
};

// Wait for DOM to be ready and other dependencies before initializing full functionality
document.addEventListener('DOMContentLoaded', function() {
    // Buckaroo Alpine.js Components for Hyva Checkout
    window.buckaroo = {
        modal() {
            return {
                showModal: false,
                title: 'Success',
                content: '',
                showClose: true,
                buttons: [],
                close() {
                    this.showModal = false;
                },
                initModal() {
                    buckaroo.start();
                    window.addEventListener('buckaroo-modal-show', (event) => {
                        if (event.detail.data) {
                            this.showModal = true;
                            Object.keys(event.detail.data).forEach((key) => {
                                this[key] = event.detail.data[key];
                            });
                        }
                    });

                    window.addEventListener('buckaroo-modal-hide', () => {
                        this.close();
                    });
                }
            };
        },
        start() {
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

            Magewire.hook('element.removed', (el, component) => {
                if (el.id !== undefined && el.id.indexOf('payment-method-view-buckaroo_magento2') > -1) {
                    window.buckarooTask = undefined;
                }
            })
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
            return Object.assign(hyva.formValidation($el), {
                oauthTokenError: '',
                paymentError: '',
                isPayButtonDisabled: false,
                hostedFieldsReady: false,
                
                async initCreditCardFields() {
                    // Initialize hosted fields when the template is ready
                    await window.buckarooHostedFields.getOAuthToken();
                },

                async resetHostedFields() {
                    await window.buckarooHostedFields.resetHostedFields();
                },

                register() {
                    // Listen for hosted fields events
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

                    // Set up the payment task for Hyva checkout
                    window.buckarooTask = async () => {
                        if (this.hostedFieldsReady && !this.isPayButtonDisabled) {
                            const paymentData = await window.buckarooHostedFields.processPayment();
                            if (paymentData) {
                                await this.$wire.updatedEncryptedData(
                                    paymentData.encryptedCardData,
                                    paymentData.service
                                );
                            } else {
                                // Update error states from the hosted fields
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
            return Object.assign(hyva.formValidation($el), {
                card:'',
                pin: '',
                cardNumber: '',
                canSubmit:false,
                async formValid() {
                    try {
                        await this.validate();
                    } catch (error) {
                        return false;
                    }
                    return true;
                },
                async submit() {
                    if(!await this.formValid()) {
                        return;
                    }
                    await this.$wire.applyGiftcard(this.card, this.cardNumber, this.pin);
                },
                displaySuccess(response) {
                    window.dispatchEvent(new CustomEvent('buckaroo-modal-show', {
                        detail: {
                            data: {
                                title: 'Success',
                                content: response.message,
                                showClose: false,
                                buttons: [{
                                    label: response.remaining_amount_message
                                }]
                            }
                        }
                    }));
                },
                displayError(message) {
                    window.dispatchEvent(new CustomEvent('buckaroo-modal-show', {
                        detail: {
                            data: {
                                title: 'Error',
                                content: message,
                            }
                        }
                    }));
                },
                listenToSubmit() {
                    this.$wire.on('giftcard_response', async (response) => {
                        if (response.error) {
                            this.displayError(response.error);
                        } else if(response.remainder_amount === undefined) {
                            this.displayError(response.message);
                        } else if (response.remainder_amount == 0) {
                            this.canSubmit = true;
                            await hyvaCheckout.order.place();
                        } else if (response.remainder_amount != 0) {
                            this.displaySuccess(response);
                        }
                        setTimeout(function () {
                            updatePosition();
                        }, 2000);
                    })
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
            return Object.assign(hyva.formValidation($el), {
                cseHasLoaded: window.buckarooCseHasLoaded,
                cardHolder: '',
                cardNumber: '',
                cardExpiration: '',
                async saveEncryptedData() {
                    let parts = this.cardExpiration.split('/');
                    const year = parts[1];
                    const month = parts[0];
                    const wire = this.$wire;
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
                        } catch (error) {
                            return false;
                        }
                        return true;
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
            return Object.assign(hyva.formValidation($el), {
                code:'',
                canSubmit:false,
                async formValid() {
                    try {
                        await this.validate();
                    } catch (error) {
                        return false;
                    }
                    return true;
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
                    window.dispatchEvent(new CustomEvent('buckaroo-modal-show', {
                        detail: {
                            data: {
                                title: 'Success',
                                content: response.message,
                                showClose: false,
                                buttons: [{
                                    label: response.remaining_amount_message
                                }]
                            }
                        }
                    }));
                },
                displayError(message) {
                    window.dispatchEvent(new CustomEvent('buckaroo-modal-show', {
                        detail: {
                            data: {
                                title: 'Error',
                                content: message,
                            }
                        }
                    }));
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
}); 