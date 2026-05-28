/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'dnapayments-api',
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader',
        'Dna_Payment/js/action/restore-quote-action',
        'Magento_Checkout/js/model/totals',
        'Dna_Payment/js/model/sync-cart-section'
    ],
    function (
        ko,
        $,
        DnapaymentsApi,
        storage,
        Component,
        globalMessageList,
        quote,
        additionalValidators,
        fullScreenLoader,
        restoreQuoteAction,
        totals,
        syncCartSection
    ) {
        'use strict';

        return Component.extend({
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            totals: totals,
            isPlaceOrderInProgress: false,
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Dna_Payment/payment/form'
            },
            initialize: function () {
                this._super();
                this.syncCartSection();
                return this;
            },
            placeOrder: function (data, event) {
                var self = this;
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }

                if (totals.isLoading()) {
                    return false;
                }

                if (this.isPlaceOrderInProgress) {
                    return false;
                }

                this.isPlaceOrderInProgress = true;
                this.isPlaceOrderActionAllowed(false);

                var isValid = this.validate();
                var additionalValid = additionalValidators.validate();

                if (!isValid || !additionalValid) {
                    this.isPlaceOrderInProgress = false;
                    this.isPlaceOrderActionAllowed(true);
                    return false;
                }

                this.getPlaceOrderDeferredObject()
                    .done(function () {
                        self.afterPlaceOrder();
                    })
                    .fail(function () {
                        self.isPlaceOrderInProgress = false;
                        self.isPlaceOrderActionAllowed(true);
                    });

                return true;
            },
            syncCartSection: function () {
                syncCartSection();
            },
            afterPlaceOrder: function () {
                this.getOrder()
            },
            getOrder(){
                const self = this;
                const requestUrl = 'rest/V1/dna-payment/start-and-get';
                let requestTimedOut = false;
                fullScreenLoader.startLoader();

                var request = storage.post(requestUrl);

                var timeoutId = setTimeout(function () {
                    requestTimedOut = true;
                    self.isPlaceOrderInProgress = false;
                    self.isPlaceOrderActionAllowed(true);
                    self.syncCartSection();
                    fullScreenLoader.stopLoader(true);
                    self.showError('Error: Timeout while starting payment session. Please try again.');

                    if (request && typeof request.abort === 'function') {
                        request.abort();
                    }
                }, 15000);

                request
                    .done(function (res) {
                        clearTimeout(timeoutId);
                        if (requestTimedOut) {
                            return;
                        }

                        try {
                            const {paymentData, auth, isTestMode, integrationType, savedCards} = (function () {
                                if (Array.isArray(res)) {
                                    const [p, a, t, i, s] = res
                                    return {paymentData: p, auth: a, isTestMode: t, integrationType: i, savedCards: s}
                                }
                                return res || {}
                            })()

                            if (!paymentData || !auth) {
                                throw new Error('Invalid start-and-get response');
                            }

                            paymentData.auth = auth;

                            const isCustomerAuthenticated = Boolean(paymentData.customerDetails && paymentData.customerDetails.accountDetails && paymentData.customerDetails.accountDetails.accountId)
                            const allowSavingCards = isCustomerAuthenticated && self.isVaultEnabled();

                            var dnaApi = window.DNAPayments;

                            var commonConfig = {
                                isTestMode: isTestMode,
                                allowSavingCards: allowSavingCards,
                                cards: allowSavingCards ? savedCards : [],
                                events: {
                                    cancelled: () => {
                                        self.isPlaceOrderInProgress = false;
                                        self.isPlaceOrderActionAllowed(true);
                                        fullScreenLoader.startLoader();
                                        restoreQuoteAction(function () {
                                            window.location.href = paymentData.paymentSettings.failureReturnUrl + '?cancel=1';
                                        });
                                    },
                                    declined: () => {
                                        self.isPlaceOrderInProgress = false;
                                        self.isPlaceOrderActionAllowed(true);
                                        fullScreenLoader.startLoader();
                                        restoreQuoteAction(function () {
                                            window.location.href = paymentData.paymentSettings.failureReturnUrl;
                                        });
                                    }
                                }
                            };

                            if (dnaApi && typeof dnaApi.configure === 'function') {
                                dnaApi.configure(commonConfig);
                            } else {
                                throw new Error('DNAPayments.configure is not available');
                            }

                            if (integrationType === '1') {
                                if (!dnaApi || typeof dnaApi.openPaymentIframeWidget !== 'function') {
                                    throw new Error('DNAPayments.openPaymentIframeWidget is not available');
                                }
                                dnaApi.openPaymentIframeWidget(paymentData);
                            } else {
                                if (!dnaApi || typeof dnaApi.openPaymentPage !== 'function') {
                                    throw new Error('DNAPayments.openPaymentPage is not available');
                                }
                                dnaApi.openPaymentPage(paymentData);
                            }
                        } catch (e) {
                            self.isPlaceOrderInProgress = false;
                            self.isPlaceOrderActionAllowed(true);
                            self.syncCartSection();
                            self.showError('Error: Failed to initialize payment window.');
                        }
                    }).fail(function (response) {
                    clearTimeout(timeoutId);
                    if (requestTimedOut) {
                        return;
                    }

                    self.isPlaceOrderInProgress = false;
                    self.isPlaceOrderActionAllowed(true);
                    self.syncCartSection();
                    self.showError('Error: Fail loading order request. Please check your credentials');
                }).always(function () {
                    if (requestTimedOut) {
                        return;
                    }

                    self.syncCartSection();
                    fullScreenLoader.stopLoader(true);
                })
            },
            getCode: function () {
                return 'dna_payment';
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': null
                };
            },
            getAddressInfo: function () {
                const address = quote.billingAddress() ? quote.billingAddress() : quote.shippingAddress();
                return {
                    accountCountry: address.countryId,
                    accountCity: address.city,
                    accountStreet1: address.street && Array.isArray(address.street) ? address.street.join(' ') : '',
                    accountEmail: this.getEmail(),
                    accountFirstName: address.firstname,
                    accountLastName: address.lastname,
                    accountPostalCode: address.postcode
                }
            },
            getEmail: function () {
                if (quote.guestEmail) return quote.guestEmail;
                else return window.checkoutConfig.customerData.email;
            },
            isVaultEnabled: function () {
                return window.checkoutConfig.payment.dna_payment.isVaultEnabled;
            },
            validate() {
                const {
                    accountCountry,
                    accountCity,
                    accountStreet1,
                    accountFirstName,
                    accountLastName,
                    accountPostalCode,
                    accountEmail
                } = this.getAddressInfo();
                let isError = false;

                if (!accountCountry || accountCountry.length > 2) {
                    this.showError('Country field is required and code length must be less than 2 symbols');
                    isError = true;
                }

                if (!accountCity || accountCity.length > 50) {
                    this.showError('City field is required and length must be less than 50 symbols');
                    isError = true;
                }

                if (!accountStreet1 || accountStreet1.length > 50) {
                    this.showError('Street field is required and length must be less than 50 symbols');
                    isError = true;
                }

                if (!accountEmail || accountEmail.length > 256) {
                    this.showError('Email field is required and length must be less than 256 symbols');
                    isError = true;
                }

                if (!accountFirstName || accountFirstName.length > 32) {
                    this.showError('Firstname field is required and length must be less than 32 symbols');
                    isError = true;
                }

                if (!accountLastName || accountLastName.length > 32) {
                    this.showError('Lastname field is required and length must be less than 32 symbols');
                    isError = true;
                }

                if (!accountPostalCode || accountPostalCode.length > 13) {
                    this.showError('Postal code field is required and length must be less than 13 symbols');
                    isError = true;
                }

                return !isError;
            },
            showError: function (errorMessage) {
                globalMessageList.addErrorMessage({
                    message: errorMessage
                });
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }
);
