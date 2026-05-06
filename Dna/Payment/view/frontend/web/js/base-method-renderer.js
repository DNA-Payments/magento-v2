/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'Dna_Payment/js/api'
    ],
    function ($, ko, Component, quote, fullScreenLoader, $t, api) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Dna_Payment/payment/form-alt',
            },
            initialize: function () {
                this.isLoading = ko.observable(false);
                this.isPaymentMethodAvailable = ko.observable(true);
                this._super();

                let self = this;
                let quoteId = quote.getQuoteId();

                quote.totals.subscribe(function (newTotals) {
                    if (newTotals && newTotals.grand_total && (self.grand_total || !self.isActive()) && self.grand_total !== newTotals.grand_total) {
                        self.renderPaymentComponent(self, quoteId);
                    }
                    self.grand_total = newTotals.grand_total;
                });

                self.renderPaymentComponent(self, quoteId);

                return this;
            },
            createPaymentComponent: function (paymentData, auth, isTestMode) {
            },
            renderPaymentComponent: function (self, quoteId) {
                self.isPaymentMethodAvailable(true);
                $('#' + self.getCode() + '_container').html('');
                self.isLoading(true);

                self.fetchQuotePaymentData(quoteId)
                    .then(async function (response) {
                        const { paymentData, auth, isTestMode } = response;
                        self.isLoading(false);
                        try {
                            self.createPaymentComponent(paymentData, auth, isTestMode);
                        } catch (error) {
                            console.error('Failed to render ' + self.getCode() + ' payment component:', error);
                            self.markPaymentMethodUnavailable();
                        }
                    })
                    .catch(function (error) {
                        console.error('Failed to fetch ' + self.getCode() + ' quote data:', error);
                        self.isLoading(false);
                        self.markPaymentMethodUnavailable();
                        fullScreenLoader.stopLoader();
                    });
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
            markPaymentMethodUnavailable: function () {
                const wasSelected = this.isChecked() === this.getCode();

                this.isLoading(false);
                fullScreenLoader.stopLoader();
                this.isPaymentMethodAvailable(false);

                if (wasSelected) {
                    this.isChecked(null);
                }
            },
            getLogo: function () {
                return window.checkoutConfig.payment[this.getCode()].logo;
            },
            getId: function () {
                return this.index;
            },
            isActive: function () {
                return this.isChecked() === this.getId();
            },
        });
    }
);
