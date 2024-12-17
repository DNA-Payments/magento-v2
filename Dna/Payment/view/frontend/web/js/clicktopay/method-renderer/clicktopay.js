/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Dna_Payment/js/base-method-renderer',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'dna-click-to-pay',
        'dnapayments-api'
    ],
    function ($, Component, fullScreenLoader, $t, dnaClickToPay, dnaApi) {
        'use strict';

        return Component.extend({
                createPaymentComponent: function (paymentData, auth) {
                    let self = this;
                    const accessToken = auth.access_token;
                    paymentData.auth = auth;

                    window.DNAPayments.ClickToPayComponent.create(
                        $('#' + self.getCode() + '_container')[0],
                        {
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
                                console.log('ClickToPayComponent error', err);

                                let message = err.message ||
                                    $t('Your card has not been authorised, please check the details and retry or contact your bank.');

                                self.showError(message);
                                fullScreenLoader.stopLoader();
                            },
                            onLoad: () => {
                                fullScreenLoader.stopLoader();
                            },
                        },
                        [],
                        accessToken,
                        paymentData
                    );
                },
                getCode: function () {
                    return 'dna_payment_clicktopay';
                },
            }
        );
    }
);