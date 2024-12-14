/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'Dna_Payment/js/api'
    ],
    function ($, Component, quote, fullScreenLoader, $t, api) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Dna_Payment/payment/form-alt',
            },
            initialize: function () {
                this._super();

                let self = this;
                let quoteId = quote.getQuoteId();

                self.fetchQuotePaymentData(quoteId)
                    .then(async function (response) {
                        const {paymentData, auth} = response;

                        self.createPaymentComponent(paymentData, auth);
                    })
                    .catch(function (error) {
                        console.error('Failed to fetch quote data:', error);

                        fullScreenLoader.stopLoader();
                    });

                return this;
            },
            createPaymentComponent: function (paymentData, auth) {
            },
            fetchQuotePaymentData: function (quoteId) {
                return api.fetchQuotePaymentData(quoteId);
            },
            showError: function (errorMessage) {
                const warningContainer = $('#' + this.getCode() + '_warning_container');
                const warningText = $('#' + this.getCode() + '_warning_text');
                warningText.text(errorMessage);
                warningContainer.show();
            },
            getLogo: function () {
                return window.checkoutConfig.payment[this.getCode()].logo;
            },
        });
    }
);
