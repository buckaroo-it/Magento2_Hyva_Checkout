/**
 * Shared helpers for Hyvä PayPal Express (cart + product pages).
 */
(function (window) {
    'use strict';

    var MAX_READY_ATTEMPTS = 20;
    var READY_DELAY_MS = 50;

    /**
     * @param {string|number|null|undefined} value
     * @returns {number|null}
     */
    function parseAmount(value) {
        var amount = parseFloat(String(value || '').replace(',', '.'), 10);

        return !isNaN(amount) && amount > 0 ? amount : null;
    }

    /**
     * @param {function(): boolean} isReady
     * @param {function(): void} callback
     * @param {number} [attempt]
     */
    function whenReady(isReady, callback, attempt) {
        attempt = attempt || 0;

        if (isReady()) {
            callback();
            return;
        }

        if (attempt < MAX_READY_ATTEMPTS) {
            setTimeout(function () {
                whenReady(isReady, callback, attempt + 1);
            }, READY_DELAY_MS);
        }
    }

    /**
     * @param {string} url
     * @param {Object} data
     * @returns {Promise}
     */
    function post(url, data) {
        if (typeof window.jQuery !== 'undefined' && window.jQuery.post) {
            return new Promise(function (resolve, reject) {
                window.jQuery.post(url, data)
                    .done(resolve)
                    .fail(function (xhr) {
                        var err = new Error(
                            xhr.responseJSON && xhr.responseJSON.message
                                ? xhr.responseJSON.message
                                : 'Request failed'
                        );
                        err.response = xhr;
                        err.body = xhr.responseJSON;
                        reject(err);
                    });
            });
        }

        var params = new URLSearchParams();

        if (typeof data === 'object') {
            Object.keys(data).forEach(function (key) {
                var value = data[key];
                if (value === null || value === undefined) {
                    return;
                }

                if (typeof value === 'object' && !Array.isArray(value)) {
                    Object.keys(value).forEach(function (nestedKey) {
                        var nestedValue = value[nestedKey];
                        if (nestedValue !== null && nestedValue !== undefined && nestedValue !== '') {
                            params.append(key + '[' + nestedKey + ']', String(nestedValue));
                        }
                    });
                } else {
                    params.append(key, String(value));
                }
            });
        }

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params.toString(),
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (responseBody) {
                    var msg = responseBody && responseBody.message
                        ? responseBody.message
                        : 'Request failed';
                    var err = new Error(msg);
                    err.response = res;
                    err.body = responseBody;
                    throw err;
                });
            }

            return res.json();
        });
    }

    /**
     * Force Hyvä to reload customer section data (e.g. cart minicart count).
     * REST API order placement does not invalidate private content like frontend POST requests.
     */
    function reloadCustomerSectionData() {
        var storage = typeof window.hyva !== 'undefined' && window.hyva.getBrowserStorage
            ? window.hyva.getBrowserStorage()
            : null;

        if (storage) {
            storage.removeItem('mage-cache-storage');
        }

        if (typeof window.hyva !== 'undefined' && window.hyva.setCookie) {
            window.hyva.setCookie('mage-cache-sessid', '', -1, true);
        }

        window.dispatchEvent(new CustomEvent('reload-customer-section-data'));
    }

    window.BuckarooHyvaPaypalExpress = {
        parseAmount: parseAmount,
        whenReady: whenReady,
        post: post,
        reloadCustomerSectionData: reloadCustomerSectionData
    };
})(window);
