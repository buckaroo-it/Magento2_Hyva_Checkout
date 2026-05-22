/**
 * Hyvä product price detection for PayPal Express (standalone script, no RequireJS).
 * Based on Buckaroo_Magento2 product-price-mixin with Hyvä-specific selectors.
 */
(function (root, factory) {
    'use strict';

    root.BuckarooExpressProductPrice = factory(root.jQuery || root.$);
}(typeof self !== 'undefined' ? self : this, function ($) {
    'use strict';

    return {
        productSelected: {
            id: null,
            qty: 1,
            unitPrice: null,
            selected_options: {}
        },

        onProductPriceChange: null,

        initProductPriceWatchers: function () {
            var self = this;

            this.productSelected.id = $('.price-box').attr('data-product-id') ||
                $('[data-product-id]').first().attr('data-product-id');

            var qtyInput = $('#qty, #product_addtocart_form input[name="qty"], input[name="qty"]').first();
            if (qtyInput.length) {
                this.productSelected.qty = parseFloat(qtyInput.val()) || 1;

                qtyInput.on('change input', function () {
                    self.productSelected.qty = parseFloat($(this).val()) || 1;
                    self.updateProductPrice();
                });
            } else {
                this.productSelected.qty = 1;
            }

            this.updateProductPrice();
            this.bindConfigurableOptionWatchers();
            this.bindHyvaPriceWatchers();
        },

        bindConfigurableOptionWatchers: function () {
            var self = this;

            $('.product-options-wrapper').on(
                'change',
                'select[name*="super_attribute"], input[name*="super_attribute"]',
                function () {
                    setTimeout(function () {
                        self.updateProductPrice();
                    }, 100);
                }
            );

            $('.product-options-wrapper div').on('click', function () {
                setTimeout(function () {
                    var selected_options = {};

                    $('div.swatch-attribute').each(function (k, v) {
                        var attribute_id = $(v).attr('attribute-id') || $(v).attr('data-attribute-id');
                        var option_selected = $(v).attr('option-selected') || $(v).attr('data-option-selected');
                        if (attribute_id && option_selected) {
                            selected_options[attribute_id] = option_selected;
                        }
                    });

                    self.productSelected.selected_options = selected_options;
                    self.updateProductPrice();
                }, 100);
            });
        },

        bindHyvaPriceWatchers: function () {
            var self = this;
            var productId = this.productSelected.id;

            if (!productId) {
                return;
            }

            var priceEventName = 'update-prices-' + productId;
            var qtyEventName = 'update-qty-' + productId;

            var handleHyvaPriceEvent = function (event) {
                if (self.applyHyvaPriceData(event && event.detail)) {
                    return;
                }
                self.updateProductPrice();
            };

            window.addEventListener(priceEventName, handleHyvaPriceEvent);
            window.addEventListener(qtyEventName, handleHyvaPriceEvent);
            window.addEventListener('update-product-final-price', handleHyvaPriceEvent);
        },

        applyHyvaPriceData: function (detail) {
            if (!detail) {
                return false;
            }

            var priceData = detail.activeProductsPriceData || detail;
            var finalPrice = priceData.finalPrice || priceData;

            if (!finalPrice || finalPrice.amount === undefined || finalPrice.amount === null) {
                return false;
            }

            var amount = parseFloat(finalPrice.amount);
            if (isNaN(amount) || amount <= 0) {
                return false;
            }

            this.productSelected.unitPrice = amount;
            this.notifyProductPriceChange();
            return true;
        },

        notifyProductPriceChange: function () {
            if (typeof this.onProductPriceChange !== 'function') {
                return;
            }

            var total = this.getProductTotalPrice();
            if (total && total > 0) {
                this.onProductPriceChange(total);
            }
        },

        updateProductPrice: function () {
            try {
                var unitPrice = this.getPriceFromDataAttribute() ||
                    this.getPriceFromWidget() ||
                    this.getPriceFromMeta() ||
                    this.getPriceFromText();

                if (unitPrice && unitPrice > 0) {
                    this.productSelected.unitPrice = unitPrice;
                    this.notifyProductPriceChange();
                }
            } catch (e) {
                console.error('[PayPal Express] Error updating product price:', e);
            }
        },

        getPriceFromDataAttribute: function () {
            var selectors = [
                '.product-info-main [data-price-type="finalPrice"] [data-price-amount]',
                '[data-price-type="finalPrice"] [data-price-amount]',
                '.price-box.price-final_price [data-price-amount]'
            ];

            for (var i = 0; i < selectors.length; i++) {
                var element = document.querySelector(selectors[i]);
                if (!element) {
                    continue;
                }

                var amount = parseFloat(element.getAttribute('data-price-amount'));
                if (!isNaN(amount) && amount > 0) {
                    return amount;
                }
            }

            return null;
        },

        getPriceFromWidget: function () {
            try {
                var priceBox = $('.product-info-main .price-box[data-product-id]').first();

                if (!priceBox.length) {
                    return null;
                }

                var priceBoxData = priceBox.data('priceBox');

                if (!priceBoxData || !priceBoxData.cache || !priceBoxData.cache.displayPrices) {
                    return null;
                }

                var finalPrice = priceBoxData.cache.displayPrices.finalPrice;

                if (finalPrice && finalPrice.amount) {
                    return parseFloat(finalPrice.amount);
                }

                return null;
            } catch (e) {
                return null;
            }
        },

        getPriceFromMeta: function () {
            var meta = document.querySelector('meta[itemprop="price"]');
            if (!meta || !meta.content) {
                return null;
            }

            var amount = parseFloat(meta.content);
            return !isNaN(amount) && amount > 0 ? amount : null;
        },

        getPriceFromText: function () {
            try {
                var selectors = [
                    '.product-info-main [data-price-type="finalPrice"] .price',
                    '[data-price-type="finalPrice"] .price',
                    '.price-box.price-final_price .price'
                ];

                for (var i = 0; i < selectors.length; i++) {
                    var priceElement = document.querySelector(selectors[i]);
                    if (!priceElement) {
                        continue;
                    }

                    var parsed = this.parsePriceText(
                        priceElement.textContent || priceElement.innerText || ''
                    );
                    if (parsed) {
                        return parsed;
                    }
                }

                return null;
            } catch (e) {
                return null;
            }
        },

        parsePriceText: function (priceText) {
            if (!priceText) {
                return null;
            }

            var priceMatch = String(priceText).trim().match(/[\d.,]+/);
            if (!priceMatch) {
                return null;
            }

            var price = priceMatch[0];

            if (/,\d{2}$/.test(price)) {
                price = price.replace(/\./g, '').replace(',', '.');
            } else {
                price = price.replace(/,/g, '');
            }

            var parsed = parseFloat(price);
            return !isNaN(parsed) && parsed > 0 ? parsed : null;
        },

        getProductPriceFromPage: function () {
            try {
                var unitPrice = this.getPriceFromDataAttribute() ||
                    this.getPriceFromWidget() ||
                    this.getPriceFromMeta() ||
                    this.getPriceFromText();

                if (unitPrice && unitPrice > 0) {
                    this.productSelected.unitPrice = unitPrice;
                    return unitPrice;
                }

                if (this.productSelected.unitPrice && this.productSelected.unitPrice > 0) {
                    return this.productSelected.unitPrice;
                }

                return null;
            } catch (e) {
                return null;
            }
        },

        getProductTotalPrice: function () {
            var unitPrice = this.getProductPriceFromPage();
            var quantity = this.productSelected.qty || 1;

            if (unitPrice && unitPrice > 0) {
                return unitPrice * quantity;
            }

            return null;
        },

        validateConfigurableOptions: function (productForm) {
            productForm = productForm || document.querySelector('#product_addtocart_form');
            if (!productForm) {
                return true;
            }

            var missingOptions = [];
            var seenAttributes = {};
            var fields = productForm.querySelectorAll('[name^="super_attribute"]');

            fields.forEach(function (field) {
                var match = field.name.match(/super_attribute\[(\d+)]/);
                if (!match || seenAttributes[match[1]]) {
                    return;
                }

                seenAttributes[match[1]] = true;
                var value = field.value;

                if (!value || value === '') {
                    missingOptions.push(this.getConfigurableAttributeLabel(match[1], productForm));
                }
            }.bind(this));

            if (missingOptions.length > 0) {
                return {
                    isValid: false,
                    message: 'Please select: ' + missingOptions.join(', ')
                };
            }

            return true;
        },

        getConfigurableAttributeLabel: function (attributeId, productForm) {
            var swatch = productForm.querySelector(
                'div.swatch-attribute[data-attribute-id="' + attributeId + '"]'
            );
            if (swatch) {
                var swatchLabel = swatch.querySelector('.swatch-attribute-label');
                if (swatchLabel) {
                    return swatchLabel.textContent.replace('*', '').trim();
                }
            }

            var select = productForm.querySelector('select[name="super_attribute[' + attributeId + ']"]');
            if (select) {
                var field = select.closest('.field');
                var selectLabel = field ? field.querySelector('label span, label') : null;
                if (selectLabel) {
                    return selectLabel.textContent.replace('*', '').trim();
                }
            }

            return 'Option';
        }
    };
}));
