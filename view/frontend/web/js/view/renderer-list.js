define([
    'Magento_Checkout/js/model/payment/renderer-list'
], function (rendererList) {
    'use strict';
    [
        'epayepicpayment',
        'epayepicpayment_anyday',
        'epayepicpayment_applepay',
        'epayepicpayment_googlepay',
        'epayepicpayment_klarna',
        'epayepicpayment_mobilepay',
        'epayepicpayment_swish',
        'epayepicpayment_viabill'
    ].forEach(function (code) {
        rendererList.push({
            type: code,
            component: 'Epay_Magento2EpicPaymentModule/js/view/payment/method-renderer/epayepicpayment'
        });
    });
    return rendererList;
});
