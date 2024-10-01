/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'mage/translate',
        'mage/storage',
        'dna-google-pay',
    ],
    function ($, Component, placeOrderAction, quote, fullScreenLoader, messageList, $t, storage, dnaGooglePay) {
        'use strict';

        return Component.extend({
                defaults: {
                    template: 'Dna_Payment/payment/form-googlepay',
                },
                initialize: function () {
                    this._super();

                    console.log('Google Pay method renderer initializing');

                    let self = this;
                    fullScreenLoader.startLoader();
                    let quoteId = quote.getQuoteId();
                    console.log('quote id', quoteId);

                    self.fetchQuotePaymentData(quoteId)
                        .then(async function (response) {
                            const {paymentData, accessToken} = response;

                            console.log('Fetched payment data', response);

                            window.DNAPayments.GooglePayComponent.create(
                                $('#dna_payment_googlepay_container'),
                                paymentData,
                                {
                                    onClick: () => {
                                        console.log('googlepay onClick');
                                        fullScreenLoader.startLoader();
                                    },
                                    onPaymentSuccess: (result) => {
                                        console.log('googlepay onPaymentSuccess result', JSON.stringify(result));

                                        fullScreenLoader.stopLoader();
                                        self.placeOrder();
                                    },
                                    onCancel: () => {
                                        console.log('googlepay onCancel');
                                        fullScreenLoader.stopLoader();
                                    },
                                    onError: (err) => {
                                        console.log('googlepay onError', err);

                                        let message = err.message ||
                                            $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                        if (err.code === 1002 || err.code === 1003) {
                                            message = $t('Google Pay payments are not supported in your current browser.');
                                        }

                                        self.showError(message);

                                        fullScreenLoader.stopLoader();
                                    },
                                    onLoad: () => {
                                        console.log('googlepay onLoad');

                                        fullScreenLoader.stopLoader();
                                    },
                                },
                                accessToken
                            );
                        })
                        .catch(function (error) {
                            console.error('Failed to fetch quote data:', error);

                            fullScreenLoader.stopLoader();
                        });

                    return this;
                },
                showError: function (errorMessage) {
                    messageList.addErrorMessage({
                        message: errorMessage
                    });
                },
                getCode: function () {
                    return 'dna_payment_googlepay';
                },
                fetchQuotePaymentData: function (quoteId) {
                    return new Promise((resolve, reject) => {
                        $.ajax({
                            url: '/rest/V1/dna-payment/get-quote-payment-data?quoteId=' + quoteId,
                            type: 'get',
                            success: function (res) {
                                const {paymentData, auth, adminOrderViewUrl} = (function () {
                                    if (Array.isArray(res)) {
                                        const [p, a, t, i, u] = res
                                        return {
                                            paymentData: p,
                                            auth: a,
                                            isTestMode: t,
                                            integrationType: i,
                                            adminOrderViewUrl: u
                                        }
                                    }
                                    return res || {}
                                })()
                                resolve({paymentData, accessToken: auth.access_token, adminOrderViewUrl});
                            },
                            error: function (err) {
                                reject(err);
                            }
                        })
                    })
                },
            }
        );
    }
);