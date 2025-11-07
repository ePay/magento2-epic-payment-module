define([
    'Magento_Checkout/js/model/payment/renderer-list'
], function (rendererList) {
    'use strict';
    rendererList.push({
        type: 'epayepicpayment',
        component: 'Epay_Magento2EpicPaymentModule/js/view/payment/method-renderer/epayepicpayment'
    });
    return rendererList;
});