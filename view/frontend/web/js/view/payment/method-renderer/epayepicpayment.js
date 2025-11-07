define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/quote',
    'ko'
], function (
    Component,
    additionalValidators,
    selectPaymentMethodAction,
    quote,
    ko
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Epay_Magento2EpicPaymentModule/payment/epayepicpayment',
            setBillingAddress: true
        },

        redirectAfterPlaceOrder: false,

        afterPlaceOrder: function () {
            window.location.replace(this.getRedirectUrl());
        },

        getDescription: function () {
                return window.checkoutConfig.payment[this.getCode()].description;
        },

        getCode: function () {
            return 'epayepicpayment';
        },

        isActive: function () {
            return true;
        },

        validate: function () {
            return this._super() && additionalValidators.validate();
        },

        /*
        selectPaymentMethod: function () {
            selectPaymentMethodAction(this.getData());
            return true;
        },
        */

        getRedirectUrl: function () {
            return window.checkoutConfig.payment[this.getCode()].redirectUrl;
        },

        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() && additionalValidators.validate()) {
                
                this.isPlaceOrderActionAllowed(false);

                selectPaymentMethodAction(this.getData());

                var redirectUrl = window.checkoutConfig.payment[this.getCode()].redirectUrl;

                this.getPlaceOrderDeferredObject().done(function() {
                    window.location.replace(redirectUrl);
                }).fail(function() {
                    self.isPlaceOrderActionAllowed(true);
                });

                return true;
            }

            return false;
        }
    });
});

