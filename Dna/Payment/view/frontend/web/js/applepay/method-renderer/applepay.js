/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Dna_Payment/js/base-method-renderer',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'dna-apple-pay',
    ],
    function ($, Component, fullScreenLoader, $t, dnaApplePay) {
        'use strict';

        return Component.extend({
                createPaymentComponent: function (paymentData, auth, isTestMode) {
                    let self = this;
                    const accessToken = auth.access_token;

                    window.DNAPayments.ApplePayComponent.init({
                            containerElement: $('#' + self.getCode() + '_container')[0],
                            paymentData: paymentData,
                            events: {
                                onClick: () => {
                                    fullScreenLoader.startLoader();
                                    $('#' + self.getCode() + '_warning_container').hide();
                                },
                                onPaymentSuccess: (result) => {
                                    fullScreenLoader.stopLoader();
                                    self.placeOrder();
                                },
                                onCancel: () => {
                                    fullScreenLoader.stopLoader();
                                },
                                onError: (err) => {
                                    console.log('ApplePayComponent error', err);

                                    let message = err.message ||
                                        $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                    if (err.code === 1002 || err.code === 1003) {
                                        message = $t('Apple Pay payments are not supported in your current browser. Please use Safari on a compatible Apple device to complete your transaction.');
                                    }

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
                    return 'dna_payment_applepay';
                },
            }
        );
    }
);