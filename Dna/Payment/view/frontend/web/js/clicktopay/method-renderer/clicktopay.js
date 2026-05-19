/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Dna_Payment/js/base-method-renderer',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Dna_Payment/js/action/restore-quote-action',
        'mage/translate',
        'mage/url',
        'Dna_Payment/js/api',
        'dna-click-to-pay',
        'dnapayments-api'
    ],
    function ($, Component, totals, fullScreenLoader, redirectOnSuccessAction, additionalValidators, restoreQuoteAction, $t, urlBuilder, api, dnaClickToPay, dnaApi) {
        'use strict';

        return Component.extend({
                createPaymentComponent: function (paymentData, auth, isTestMode) {
                    let self = this;
                    const accessToken = auth.access_token;
                    self.isPaymentSuccessful = false;
                    paymentData.auth = auth;

                    window.DNAPayments.ClickToPayComponent.init(
                        {
                            containerElement: $('#' + self.getCode() + '_container')[0],
                            paymentData: paymentData,
                            events: {
                                onClick: () => {
                                    if (totals.isLoading()) {
                                        return false;
                                    }

                                    fullScreenLoader.startLoader();
                                    $('#' + self.getCode() + '_warning_container').hide();
                                    return {};
                                },
                                onBeforeProcessPayment: () => {
                                    return new Promise((resolve, reject) => {
                                        if (!additionalValidators.validate()) {
                                            fullScreenLoader.stopLoader();
                                            reject(new Error('Validation failed'));
                                            return;
                                        }
                                        self.getPlaceOrderDeferredObject()
                                            .done(function (orderId) {
                                                self.orderId = orderId;
                                                api.fetchOrderPaymentData(orderId)
                                                    .then(function (response) {
                                                        resolve({
                                                            paymentData: response.paymentData,
                                                            token: response.auth ? response.auth.access_token : undefined
                                                        });
                                                    })
                                                    .catch(function (error) {
                                                        restoreQuoteAction(self.orderId);
                                                        self.orderId = null;
                                                        fullScreenLoader.stopLoader();
                                                        reject(error);
                                                    });
                                            })
                                            .fail(function (response) {
                                                fullScreenLoader.stopLoader();
                                                reject(response);
                                            });
                                    });
                                },
                                onPaymentSuccess: (result) => {
                                    self.isPaymentSuccessful = true;
                                    self.orderId = null;
                                    fullScreenLoader.stopLoader();
                                    redirectOnSuccessAction.execute();
                                },
                                onCancel: () => {
                                    if (self.isPaymentSuccessful) {
                                        fullScreenLoader.stopLoader();
                                        return;
                                    }
                                    fullScreenLoader.startLoader();

                                    if (!self.orderId) {
                                        window.location.href = urlBuilder.build('checkout/cart');
                                        return;
                                    }

                                    restoreQuoteAction(self.orderId, function () {
                                        self.orderId = null;
                                        window.location.href = paymentData.paymentSettings.failureReturnUrl + '?cancel=1';
                                    });
                                },
                                onError: (err) => {
                                    if (self.isPaymentSuccessful) {
                                        fullScreenLoader.stopLoader();
                                        return;
                                    }
                                    err = err || {};
                                    console.log('ClickToPayComponent error', err);

                                    let message = err.message ||
                                        $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                    if (!self.orderId) {
                                        self.showError(message);
                                        return;
                                    }

                                    self.showError(message);
                                    fullScreenLoader.startLoader();
                                    restoreQuoteAction(self.orderId, function () {
                                        self.orderId = null;
                                        window.location.href = paymentData.paymentSettings.failureReturnUrl;
                                    });
                                },
                                onLoad: () => {
                                    fullScreenLoader.stopLoader();
                                },
                            },
                            cardBrands: [],
                            token: accessToken,
                            environment: isTestMode ? 'sandbox' : 'production'
                        }
                    );
                },
                getCode: function () {
                    return 'dna_payment_clicktopay';
                },
            }
        );
    }
);
