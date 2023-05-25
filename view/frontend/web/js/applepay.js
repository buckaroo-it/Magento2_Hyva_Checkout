/* BEGIN ########################################################### */
/* Added: */
/* eslint-disable */
/* END ############################################################# */
/*!
 * Buckaroo Client SDK v1.8.0
 *
 * Copyright Buckaroo
 * Released under the MIT license
 * https://buckaroo.nl
 *
 * Date: 2019-10-03 11:26
 */

const checkPaySupport = async function (merchantIdentifier) {
    if (!('ApplePaySession' in window)) return false;
    if (ApplePaySession === undefined) return false;
    return await ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
  };
  const getButtonClass = function (buttonStyle, buttonType) {
    if (buttonStyle === void 0) {
      buttonStyle = 'black';
    }
    if (buttonType === void 0) {
      buttonType = 'plain';
    }
  
    let classes = ['apple-pay','apple-pay-button'];
  
    switch (buttonType) {
      case 'plain':
        classes.push('apple-pay-button-type-plain');
        break;
      case 'book':
        classes.push('apple-pay-button-type-book');
        break;
      case 'buy':
        classes.push('apple-pay-button-type-buy');
        break;
      case 'check-out':
        classes.push('apple-pay-button-type-check-out');
        break;
      case 'donate':
        classes.push('apple-pay-button-type-donate');
        break;
      case 'set-up':
        classes.push('apple-pay-button-type-set-up');
        break;
      case 'subscribe':
        classes.push('apple-pay-button-type-subscribe');
        break;
    }
    switch (buttonStyle) {
      case 'black':
        classes.push('apple-pay-button-black');
        break;
      case 'white':
        classes.push('apple-pay-button-white');
        break;
      case 'white-outline':
        classes.push('apple-pay-button-white-with-line');
        break;
    }
    return classes.join(" ");
  };
  var PayPayment = function (options) {
    var _this = this;
    this.applePayVersion = 4;
    this.validationUrl = 'https://applepay.buckaroo.io/v1/request-session';
    /**
     * Aborts the current ApplePaySession if exists.
     */
    this.abortSession = function () {
      if (_this.session) {
        _this.session.abort();
      }
    };
    /**
     * Initializes the ApplePay button
     */
    this.init = function () {
      if (document.getElementById('buckaroo-sdk-css') === null) {
        document.head.insertAdjacentHTML("beforeend", "<link id=\"buckaroo-sdk-css\" href=\"https://checkout.buckaroo.nl/api/buckaroosdk/css\" rel=\"stylesheet\">");
      }
    }
  
    this.validate = function () {
      if (!_this.options.processCallback)
        throw 'ApplePay: processCallback must be set';
      if (!_this.options.storeName) throw 'ApplePay: storeName is not set';
      if (!_this.options.countryCode) throw 'ApplePay: countryCode is not set';
      if (!_this.options.currencyCode) throw 'ApplePay: currencyCode is not set';
      if (!_this.options.merchantIdentifier)
        throw 'ApplePay: merchantIdentifier is not set';
    };
    this.beginPayment = function () {

        // _this.options.processCallback({}).then(function (authorizationResult) {
        //    console.log(authorizationResult);
        // });
        // return;

      var paymentRequest = {
        countryCode: _this.options.countryCode,
        currencyCode: _this.options.currencyCode,
        merchantCapabilities: _this.options.merchantCapabilities,
        supportedNetworks: _this.options.supportedNetworks,
        lineItems: _this.options.lineItems,
        total: _this.options.totalLineItem,
        requiredBillingContactFields: _this.options.requiredBillingContactFields,
        requiredShippingContactFields:
          _this.options.requiredShippingContactFields,
        shippingType: _this.options.shippingType,
        shippingMethods: _this.options.shippingMethods,
      };
      // Create the Apple Pay session.
      _this.session = new ApplePaySession(_this.applePayVersion, paymentRequest);
      // Setup handler for validation the merchant session.
      _this.session.onvalidatemerchant = _this.onValidateMerchant;
      // Setup handler for shipping method selection.
      if (_this.options.shippingMethodSelectedCallback)
        _this.session.onshippingmethodselected = _this.onShippingMethodSelected;
      // Setup handler for shipping contact selection.
      if (_this.options.shippingContactSelectedCallback)
        _this.session.onshippingcontactselected = _this.onShippingContactSelected;
      // Setup handler for shipping method selection.
      if (_this.options.cancelCallback) _this.session.oncancel = _this.onCancel;
      // Setup handler to receive the token when payment is authorized.
      _this.session.onpaymentauthorized = _this.onPaymentAuthorized;
      // Begin the session to display the Apple Pay sheet.
      _this.session.begin();
    };
    /**
     * Handles merchant validation for the Apple Pay session.
     * @param event - The ApplePayValidateMerchantEvent object.
     */
    this.onValidateMerchant = function (event) {
      // Create the payload.
      var data = {
        validationUrl: event.validationURL,
        displayName: _this.options.storeName,
        domainName: window.location.hostname,
        merchantIdentifier: _this.options.merchantIdentifier,
      };
      // Post the payload to the server to validate the
      // merchant session using the merchant certificate.
      fetch(_this.validationUrl, {
        method: 'POST',
        body: JSON.stringify(data),
      }).then((response) => response.json()).then(function (response) {
        // Complete validation by passing the merchant session to the Apple Pay session.
        _this.session.completeMerchantValidation(response);
      });
    };
    /**
     * Handles the Apple Pay payment being authorized by the user.
     * @param event - The ApplePayPaymentAuthorizedEvent object.
     */
    this.onPaymentAuthorized = function (event) {
      // console.log('auth', event);
      // Get the payment data for use to capture funds from
      // the encrypted Apple Pay token in your server.
      var payment = event.payment;
      // Process the payment
      _this.options.processCallback(payment).then(function (authorizationResult) {
        // Complete payment
        _this.session.completePayment(authorizationResult);
      });
    };
    /**
     * Handles the shipping method being changed by the user
     * @param event - The ApplePayShippingMethodSelectedEvent object.
     */
    this.onShippingMethodSelected = function (event) {
      if (!_this.options.shippingMethodSelectedCallback) return;
      _this.options
        .shippingMethodSelectedCallback(event.shippingMethod)
        .then(function (result) {
          if (!result) return;
          _this.session.completeShippingMethodSelection(result);
        });
    };
    /**
     * Handles the shipping contact being changed by the user
     * @param event - The ApplePayShippingContactSelectedEvent object.
     */
    this.onShippingContactSelected = function (event) {
      if (!_this.options.shippingContactSelectedCallback) return;
      _this.options
        .shippingContactSelectedCallback(event.shippingContact)
        .then(function (result) {
          if (!result) return;
          _this.session.completeShippingContactSelection(result);
        });
    };
    /**
     * An event handler that is automatically called when the payment UI is dismissed.
     * @param event - The Event object.
     */
    this.onCancel = function (event) {
      if (!_this.options.cancelCallback) return;
      _this.options.cancelCallback(event);
    };
    this.options = options;

    this.init();
    this.validate();
  };
  var PayOptions = function(
    storeName,
    countryCode,
    currencyCode,
    cultureCode,
    merchantIdentifier,
    lineItems,
    totalLineItem,
    shippingType,
    shippingMethods,
    processCallback,
    shippingMethodSelectedCallback,
    shippingContactSelectedCallback,
    requiredBillingContactFields,
    requiredShippingContactFields,
    cancelCallback,
    merchantCapabilities,
    supportedNetworks
  ) {
    if (shippingMethodSelectedCallback === void 0) {
      shippingMethodSelectedCallback = null;
    }
    if (shippingContactSelectedCallback === void 0) {
      shippingContactSelectedCallback = null;
    }
    if (requiredBillingContactFields === void 0) {
      requiredBillingContactFields = ['email', 'name', 'postalAddress'];
    }
    if (requiredShippingContactFields === void 0) {
      requiredShippingContactFields = ['email', 'name', 'postalAddress'];
    }
    if (cancelCallback === void 0) {
      cancelCallback = null;
    }
    if (merchantCapabilities === void 0) {
      merchantCapabilities = ['supports3DS', 'supportsCredit', 'supportsDebit'];
    }
    if (supportedNetworks === void 0) {
      supportedNetworks = [
        'masterCard',
        'visa',
        'maestro',
        'vPay',
        'cartesBancaires',
        'privateLabel',
      ];
    }
    this.storeName = storeName;
    this.countryCode = countryCode;
    this.currencyCode = currencyCode;
    this.cultureCode = cultureCode;
    this.merchantIdentifier = merchantIdentifier;
    this.lineItems = lineItems;
    this.totalLineItem = totalLineItem;
    this.shippingType = shippingType;
    this.shippingMethods = shippingMethods;
    this.processCallback = processCallback;
    this.shippingMethodSelectedCallback = shippingMethodSelectedCallback;
    this.shippingContactSelectedCallback = shippingContactSelectedCallback;
    this.requiredBillingContactFields = requiredBillingContactFields;
    this.requiredShippingContactFields = requiredShippingContactFields;
    this.cancelCallback = cancelCallback;
    this.merchantCapabilities = merchantCapabilities;
    this.supportedNetworks = supportedNetworks;
  };
  
  var BuckarooApplePay = {
    PayPayment,
    PayOptions,
    checkPaySupport,
    getButtonClass
  };
