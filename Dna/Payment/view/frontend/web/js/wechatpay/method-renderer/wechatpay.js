/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Dna_Payment/js/base-method-renderer',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'dna-alipay-wechat-pay',
    ],
    function ($, Component, fullScreenLoader, $t, dnaWeChatPay) {
        'use strict';

        return Component.extend({
                createPaymentComponent: function (paymentData, auth, isTestMode) {
                    let self = this;
                    const accessToken = auth.access_token;
                    window.DNAPayments.WeChatPayComponent.init(
                        {
                            containerElement: $('#' + self.getCode() + '_container')[0],
                            paymentData: paymentData,
                            events: {
                                onClick: () => {
                                    fullScreenLoader.startLoader();
                                    $('#' + self.getCode() + '_warning_container').hide();
                                    return {};
                                },
                                onPaymentSuccess: (result) => {
                                    fullScreenLoader.stopLoader();
                                    self.placeOrder();
                                },
                                onCancel: () => {
                                    fullScreenLoader.stopLoader();
                                },
                                onError: (err) => {
                                    console.log('WeChatPayComponent error', err);

                                    let message = err.message ||
                                        $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                    self.showError(message);
                                    fullScreenLoader.stopLoader();
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
                    return 'dna_payment_wechatpay';
                },
            }
        );
    }
);