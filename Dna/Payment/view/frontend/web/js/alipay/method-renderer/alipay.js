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
        'dna-alipay-plus',
    ],
    function ($, Component, placeOrderAction, quote, fullScreenLoader, messageList, $t, storage, dnaAlipay) {
        'use strict';

        return Component.extend({
                defaults: {
                    template: 'Dna_Payment/payment/form-alipay',
                },
                initialize: function () {
                    this._super();

                    console.log('Alipay method renderer initializing');

                    let self = this;
                    // fullScreenLoader.startLoader();
                    let quoteId = quote.getQuoteId();
                    console.log('quote id', quoteId);

                    self.fetchQuotePaymentData(quoteId)
                        .then(async function (response) {
                            const {paymentData, accessToken} = response;

                            console.log('Alipay Fetched payment data', response);

                            window.DNAPayments.AlipayPlusComponent.create(
                                $('#dna_payment_alipay_container')[0],
                                {
                                    onClick: () => {
                                        console.log('Alipay+ button has been clicked');
                                        fullScreenLoader.startLoader();
                                        return {};
                                    },
                                    onPaymentSuccess: (result) => {
                                        fullScreenLoader.stopLoader();
                                        self.placeOrder();
                                    },
                                    onCancel: () => {
                                        console.log('AlipayPlusComponent is cancelled');
                                        fullScreenLoader.stopLoader();
                                    },
                                    onError: (err) => {
                                        console.log('AlipayPlusComponent error', err);
                                        console.log('AlipayPlusComponent error code', err.code);
                                        console.log('AlipayPlusComponent error message', err.message);

                                        let message = err.message ||
                                            $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                        if (err.code === 1002 || err.code === 1003) {
                                            // TODO: should we process this error codes?
                                        } else {
                                            self.showError(message);
                                        }

                                        fullScreenLoader.stopLoader();
                                    },
                                    onLoad: () => {
                                        console.log('AlipayPlusComponent is loaded');

                                        fullScreenLoader.stopLoader();
                                    },
                                },
                                {
                                    token: accessToken,
                                    paymentData: paymentData
                                },
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
                    return 'dna_payment_alipay_plus';
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