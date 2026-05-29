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
        'Dna_Payment/js/api',
        'dna-alipay-wechat-pay'
    ],
    function ($, Component, totals, fullScreenLoader, redirectOnSuccessAction, additionalValidators, restoreQuoteAction, $t, api, dnaAlipay) {
        'use strict';

        return Component.extend({
                createPaymentComponent: function (paymentData, auth, isTestMode) {
                    let self = this;
                    const accessToken = auth.access_token;
                    self.isPaymentSuccessful = false;

                    window.DNAPayments.AlipayPlusComponent.init(
                        {
                            containerElement: $('#' + self.getCode() + '_container')[0],
                            paymentData: paymentData,
                            events: {
                                onClick: () => {
                                    if (totals.isLoading()) {
                                        return false;
                                    }

                                    self.startPaymentLoading();
                                    $('#' + self.getCode() + '_warning_container').hide();

                                    return {};
                                },
                                onBeforeProcessPayment: () => {
                                    self.markPaymentProcessingStarted();

                                    return new Promise((resolve, reject) => {
                                        if (!additionalValidators.validate()) {
                                            self.resetPaymentLoading(false, 'validation failed before order');
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
                                                        restoreQuoteAction(self.orderId).always(function () {
                                                            self.orderId = null;
                                                            self.resetPaymentLoading(true, 'fetch order payment data failed after order');
                                                            reject(error);
                                                        });
                                                    });
                                            })
                                            .fail(function (response) {
                                                self.resetPaymentLoading(true, 'place order failed');
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
                                        self.resetPaymentLoading(false, 'cancel after successful payment');
                                        return;
                                    }
                                    fullScreenLoader.startLoader();

                                    if (!self.orderId) {
                                        self.resetPaymentLoading(false, 'cancel without order');
                                        return;
                                    }

                                    restoreQuoteAction(self.orderId).always(function () {
                                        self.orderId = null;
                                        self.resetPaymentLoading(true, 'cancel after restore quote');
                                    });
                                },
                                onError: (err) => {
                                    if (self.isPaymentSuccessful) {
                                        fullScreenLoader.stopLoader();
                                        return;
                                    }
                                    err = err || {};

                                    let message = err.message ||
                                        $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                    if (!self.orderId) {
                                        self.resetPaymentLoading(false, 'error without order');
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
                            token: accessToken,
                            environment: isTestMode ? 'sandbox' : 'production'
                        }
                    );
                },
                getCode: function () {
                    return 'dna_payment_alipay_plus';
                }
            }
        );
    }
);
