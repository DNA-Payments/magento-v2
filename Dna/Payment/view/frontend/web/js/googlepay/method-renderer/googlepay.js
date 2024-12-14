/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Dna_Payment/js/base-method-renderer',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'dna-google-pay',
    ],
    function ($, Component, fullScreenLoader, $t, dnaGooglePay) {
        'use strict';

        return Component.extend({
                createPaymentComponent: function (paymentData, auth) {
                    let self = this;
                    const accessToken = auth.access_token;

                    window.DNAPayments.GooglePayComponent.create(
                        $('#' + self.getCode() + '_container')[0],
                        paymentData,
                        {
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
                                console.log('GooglePayComponent error', err);

                                let message = err.message ||
                                    $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                if (err.code === 1002 || err.code === 1003) {
                                    message = $t('Google Pay payments are not supported in your current browser.');
                                }

                                self.showError(message);
                                fullScreenLoader.stopLoader();
                            },
                            onLoad: () => {
                                fullScreenLoader.stopLoader();
                            },
                        },
                        accessToken
                    );
                },
                getCode: function () {
                    return 'dna_payment_googlepay';
                },
            }
        );
    }
);