define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/messageList',
    'ko'
], function (
    Component,
    fullScreenLoader,
    additionalValidators,
    selectPaymentMethodAction,
    quote,
    globalMessageList,
    ko
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Epay_Magento2EpicPaymentModule/payment/epayepicpayment',
            setBillingAddress: true
        },

        redirectAfterPlaceOrder: false,

        getDescription: function () {
                return window.checkoutConfig.payment[this.getCode()].description;
        },

        getCode: function () {
            return 'epayepicpayment';
        },

        isActive: function () {
            return true;
        },
        selectPaymentMethod: function () {
            selectPaymentMethodAction(this.getData());
            return true;
        },
        validate: function () {
            return this._super() && additionalValidators.validate();
        },
        afterPlaceOrder: function () {

            fullScreenLoader.startLoader();

            var cfg = window.checkoutConfig.payment[this.getCode()] || {};
            var redirectUrl = cfg.redirectUrl;

            if (!redirectUrl) {
                fullScreenLoader.stopLoader();
                this.messageContainer.addErrorMessage({message: 'Redirect URL mangler (redirectUrl).'});
                return;
            }

            window.location.replace(redirectUrl);
        }
    });
});

