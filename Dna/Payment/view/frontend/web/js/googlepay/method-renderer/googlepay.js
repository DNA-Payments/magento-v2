/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Dna_Payment/js/base-method-renderer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Dna_Payment/js/action/restore-quote-action',
        'mage/translate',
        'Dna_Payment/js/api',
        'dna-google-pay'
    ],
    function ($, Component, fullScreenLoader, redirectOnSuccessAction, additionalValidators, restoreQuoteAction, $t, api, dnaGooglePay) {
        'use strict';

        return Component.extend({
                createPaymentComponent: function (paymentData, auth, isTestMode) {
                    let self = this;
                    const accessToken = auth.access_token;

                    window.DNAPayments.GooglePayComponent.init(
                        {
                            containerElement: $('#' + self.getCode() + '_container')[0],
                            paymentData: paymentData,
                            events: {
                                onClick: () => {
                                    fullScreenLoader.startLoader();
                                    $('#' + self.getCode() + '_warning_container').hide();
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
                                    fullScreenLoader.stopLoader();
                                    redirectOnSuccessAction.execute();
                                },
                                onCancel: () => {
                                    fullScreenLoader.startLoader();
                                    restoreQuoteAction(self.orderId, function () {
                                        self.orderId = null;
                                        window.location.href = paymentData.paymentSettings.failureReturnUrl + '?cancel=1';
                                    });
                                },
                                onError: (err) => {
                                    console.log('GooglePayComponent error', err);

                                    let message = err.message ||
                                        $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                    if (err.code === 1002 || err.code === 1003) {
                                        message = $t('Google Pay payments are not supported in your current browser.');
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
                            token: accessToken,
                            environment: isTestMode ? 'sandbox' : 'production'
                        }
                    );
                },
                getCode: function () {
                    return 'dna_payment_googlepay';
                },
            }
        );
    }
);