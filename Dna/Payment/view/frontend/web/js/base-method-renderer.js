/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'Dna_Payment/js/api',
        'Dna_Payment/js/action/restore-quote-action'
    ],
    function ($, ko, Component, quote, totals, fullScreenLoader, $t, api, restoreQuoteAction) {
        'use strict';

        return Component.extend({
            totals: totals,
            defaults: {
                template: 'Dna_Payment/payment/form-alt',
            },
            initialize: function () {
                this.isLoading = ko.observable(false);
                this.isPaymentMethodAvailable = ko.observable(true);
                this.paymentProcessingStarted = false;
                this.isPaymentSuccessful = false;
                this._super();

                let self = this;
                let quoteId = quote.getQuoteId();
                this.quoteId = quoteId;

                totals.isLoading.subscribe(function (isLoading) {
                    $('#' + self.getCode() + '_container').toggleClass('_totals-loading', isLoading);
                });

                quote.totals.subscribe(function (newTotals) {
                    if (newTotals && newTotals.grand_total && self.grand_total && self.grand_total !== newTotals.grand_total) {
                        self.renderPaymentComponent(self, quoteId);
                    }
                    self.grand_total = newTotals.grand_total;
                });

                self.renderPaymentComponent(self, quoteId);

                return this;
            },
            createPaymentComponent: function (paymentData, auth, isTestMode) {
            },
            getQuotePaymentDataCacheKey: function (quoteId) {
                var currentTotals = quote.totals() || {};

                return [
                    quoteId || '',
                    currentTotals.grand_total || '',
                    currentTotals.quote_currency_code || ''
                ].join('|');
            },
            renderPaymentComponent: function (self, quoteId) {
                var cacheKey = self.getQuotePaymentDataCacheKey(quoteId);

                if (self.isRenderingPaymentComponent && self.renderingPaymentDataCacheKey === cacheKey) {
                    return;
                }

                self.isRenderingPaymentComponent = true;
                self.renderingPaymentDataCacheKey = cacheKey;
                self.isPaymentMethodAvailable(true);
                $('#' + self.getCode() + '_container').empty();
                self.isLoading(true);

                self.fetchQuotePaymentData(quoteId, cacheKey)
                    .then(async function (response) {
                        const { paymentData, auth, isTestMode } = response;
                        self.isLoading(false);
                        self.clearPaymentLoadingMask();
                        try {
                            self.createPaymentComponent(paymentData, auth, isTestMode);
                        } catch (error) {
                            self.markPaymentMethodUnavailable();
                        }
                    })
                    .catch(function (error) {
                        self.isLoading(false);
                        self.clearPaymentLoadingMask();
                        self.markPaymentMethodUnavailable();
                        fullScreenLoader.stopLoader();
                    })
                    .finally(function () {
                        self.isRenderingPaymentComponent = false;
                    });
            },
            fetchQuotePaymentData: function (quoteId, cacheKey) {
                return api.fetchQuotePaymentData(quoteId, cacheKey);
            },
            showError: function (errorMessage) {
                const warningContainer = $('#' + this.getCode() + '_warning_container');
                const warningText = $('#' + this.getCode() + '_warning_text');
                warningText.text(errorMessage);
                warningContainer.show();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            startPaymentLoading: function () {
                var self = this;

                this.paymentProcessingStarted = false;
                this.isPaymentSuccessful = false;
                fullScreenLoader.startLoader();
                this.clearPaymentLoadingWatch();

                this.paymentLoadingWatchHandler = function () {
                    if (document.visibilityState && document.visibilityState !== 'visible') {
                        return;
                    }

                    self.paymentLoadingWatchTimeout = window.setTimeout(function () {
                        self.handleSilentPaymentCancel();
                    }, 250);
                };

                window.addEventListener('focus', this.paymentLoadingWatchHandler);
                document.addEventListener('visibilitychange', this.paymentLoadingWatchHandler);
            },
            markPaymentProcessingStarted: function () {
                this.paymentProcessingStarted = true;
                this.clearPaymentLoadingWatch();

                fullScreenLoader.startLoader();
            },
            handleSilentPaymentCancel: function () {
                var self = this;

                if (this.isPaymentSuccessful || this.isRestoringPaymentCancel || this.paymentProcessingStarted) {
                    return;
                }

                if (!this.orderId) {
                    this.resetPaymentLoading(false, 'silent cancel without order');
                    return;
                }

                this.isRestoringPaymentCancel = true;
                restoreQuoteAction(this.orderId).always(function () {
                    self.orderId = null;
                    self.isRestoringPaymentCancel = false;
                    self.resetPaymentLoading(true, 'silent cancel after restore quote');
                });
            },
            clearPaymentLoadingWatch: function () {
                if (this.paymentLoadingWatchTimeout) {
                    window.clearTimeout(this.paymentLoadingWatchTimeout);
                    this.paymentLoadingWatchTimeout = null;
                }

                if (this.paymentLoadingWatchHandler) {
                    window.removeEventListener('focus', this.paymentLoadingWatchHandler);
                    document.removeEventListener('visibilitychange', this.paymentLoadingWatchHandler);
                    this.paymentLoadingWatchHandler = null;
                }
            },
            clearPaymentLoadingMask: function () {
                var methodLoader = $('#' + this.getCode() + '_container')
                    .siblings('.dna-payment-method-loader');

                if (methodLoader.length && typeof methodLoader.unblock === 'function') {
                    methodLoader.unblock();
                }

                $('#' + this.getCode() + '_container').removeClass('_totals-loading');
            },
            resetPaymentLoading: function (rerenderPaymentComponent, reason) {
                var self = this;
                var shouldRerenderPaymentComponent = Boolean(rerenderPaymentComponent);

                this.clearPaymentLoadingWatch();

                if (this.paymentRerenderTimeout) {
                    window.clearTimeout(this.paymentRerenderTimeout);
                    this.paymentRerenderTimeout = null;
                }

                if (this.paymentFallbackLoaderTimeout) {
                    window.clearTimeout(this.paymentFallbackLoaderTimeout);
                    this.paymentFallbackLoaderTimeout = null;
                }

                this.paymentProcessingStarted = false;
                this.isLoading(false);
                this.clearPaymentLoadingMask();
                fullScreenLoader.stopLoader(true);

                if (shouldRerenderPaymentComponent && this.quoteId) {
                    api.clearCache();
                    this.isLoading(true);

                    this.paymentRerenderTimeout = window.setTimeout(function () {
                        self.paymentRerenderTimeout = null;
                        self.isRenderingPaymentComponent = false;
                        self.renderingPaymentDataCacheKey = null;
                        self.renderPaymentComponent(self, self.quoteId);

                        self.paymentFallbackLoaderTimeout = window.setTimeout(function () {
                            self.paymentFallbackLoaderTimeout = null;
                            self.isLoading(false);
                            self.clearPaymentLoadingMask();
                            fullScreenLoader.stopLoader(true);
                        }, 1500);
                    }, 0);
                }
            },
            markPaymentMethodUnavailable: function () {
                const wasSelected = this.isChecked() === this.getCode();

                this.isLoading(false);
                fullScreenLoader.stopLoader(true);
                this.isPaymentMethodAvailable(false);

                if (wasSelected) {
                    quote.paymentMethod(null);
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
