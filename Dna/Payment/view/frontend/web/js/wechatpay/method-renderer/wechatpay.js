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
        'dna-wechat-pay',
    ],
    function ($, Component, placeOrderAction, quote, fullScreenLoader, messageList, $t, storage, dnaWeChatPay) {
        'use strict';

        return Component.extend({
                defaults: {
                    template: 'Dna_Payment/payment/form-wechatpay',
                },
                initialize: function () {
                    this._super();

                    console.log('WeChatPay method renderer initializing');

                    let self = this;
                    // fullScreenLoader.startLoader();
                    let quoteId = quote.getQuoteId();
                    console.log('quote id', quoteId);

                    self.fetchQuotePaymentData(quoteId)
                        .then(async function (response) {
                            const {paymentData, accessToken} = response;

                            console.log('WeChatPay Fetched payment data', response);

                            window.DNAPayments.WeChatPayComponent.create(
                                $('#dna_payment_wechatpay_container')[0],
                                {
                                    onClick: () => {
                                        console.log('WeChatPayComponent has been clicked');
                                        fullScreenLoader.startLoader();
                                        return {};
                                    },
                                    onPaymentSuccess: (result) => {
                                        fullScreenLoader.stopLoader();
                                        self.placeOrder();
                                    },
                                    onCancel: () => {
                                        console.log('WeChatPayComponent is cancelled');
                                        fullScreenLoader.stopLoader();
                                    },
                                    onError: (err) => {
                                        console.log('WeChatPayComponent error', err);
                                        console.log('WeChatPayComponent error code', err.code);
                                        console.log('WeChatPayComponent error message', err.message);

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
                                        console.log('WeChatPayComponent is loaded');

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
                    return 'dna_payment_wechatpay';
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