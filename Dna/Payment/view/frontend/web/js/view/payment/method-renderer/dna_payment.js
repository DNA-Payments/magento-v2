/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'dnapayments-api',
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (
        ko,
        $,
        DnapaymentsApi,
        storage,
        Component,
        globalMessageList,
        quote,
        fullScreenLoader
    ) {
        'use strict';
        return Component.extend({
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Dna_Payment/payment/form'
            },
            afterPlaceOrder: function () {
                this.getOrder()
            },
            getOrder(){
                const self = this;
                fullScreenLoader.startLoader();
                storage.post('rest/default/V1/dna-payment/start-and-get')
                    .done(function (res) {
                            
                        const { paymentData, auth, isTestMode, integrationType } = (function() {
                            if (Array.isArray(res)) {
                                const [p, a, t, i] = res
                                return { paymentData: p, auth: a, isTestMode: t, integrationType: i }
                            }
                            return res || {}
                        })()

                        paymentData.auth = auth;
                        window.DNAPayments.configure({
                            isTestMode
                        });
                    
                        if (integrationType === '1') {
                            window.DNAPayments.openPaymentIframeWidget(paymentData);
                        } else {
                            window.DNAPayments.openPaymentPage(paymentData);
                        }
                    }).fail(function (response) {
                        self.showError('Error: Fail loading order request. Please check your credentials');
                    }).always(function () {
                        fullScreenLoader.stopLoader(true);
                })
            },
            getCode: function() {
                return 'dna_payment';
            },
            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': null
                };
            },
            getAddressInfo: function() {
                const address = quote.billingAddress() ? quote.billingAddress() : quote.shippingAddress();
                return {
                    accountCountry: address.countryId,
                    accountCity: address.city,
                    accountStreet1: address.street && Array.isArray(address.street) ? address.street.join(' ') : '',
                    accountEmail: this.getEmail(),
                    accountFirstName: address.firstname,
                    accountLastName: address.lastname,
                    accountPostalCode: address.postcode
                }
            },
            getEmail: function () {
                if(quote.guestEmail) return quote.guestEmail;
                else return window.checkoutConfig.customerData.email;
            },
            validate() {
                const { accountCountry, accountCity, accountStreet1, accountFirstName, accountLastName, accountPostalCode, accountEmail } = this.getAddressInfo();
                let isError = false;

                if(!accountCountry || accountCountry.length > 2) {
                    this.showError('Country field is required and code length must be less than 2 symbols');
                    isError = true;
                }

                if(!accountCity || accountCity.length > 50) {
                    this.showError('City field is required and length must be less than 50 symbols');
                    isError = true;
                }

                if(!accountStreet1 || accountStreet1.length > 50) {
                    this.showError('Street field is required and length must be less than 50 symbols');
                    isError = true;
                }

                if(!accountEmail || accountEmail.length > 256) {
                    this.showError('Email field is required and length must be less than 256 symbols');
                    isError = true;
                }

                if(!accountFirstName || accountFirstName.length > 32) {
                    this.showError('Firstname field is required and length must be less than 32 symbols');
                    isError = true;
                }

                if(!accountLastName || accountLastName.length > 32) {
                    this.showError('Lastname field is required and length must be less than 32 symbols');
                    isError = true;
                }

                if(!accountPostalCode || accountPostalCode.length > 13) {
                    this.showError('Postal code field is required and length must be less than 13 symbols');
                    isError = true;
                }

                return !isError;
            },
            showError: function (errorMessage) {
                globalMessageList.addErrorMessage({
                    message: errorMessage
                });
            }
        });
    }
);
