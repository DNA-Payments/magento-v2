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
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'dna-payment-api'
    ],
    function (
        ko,
        $,
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
                template: 'Dna_Payment/payment/form',
                orderId: null,
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'orderId'
                    ]);
                this.grandTotalAmount = quote.totals()['base_grand_total'];

                quote.totals.subscribe(function () {
                    if (self.grandTotalAmount !== quote.totals()['base_grand_total']) {
                        self.grandTotalAmount = quote.totals()['base_grand_total'];
                    }
                });

                quote.billingAddress.subscribe(function (address) {
                    this.isPlaceOrderActionAllowed(address !== null);
                }, this);

                return this;
            },
            afterPlaceOrder: function () {
                this.getOrder()
            },
            getOrder(){
                const self = this;
                fullScreenLoader.startLoader();
                storage.post('rest/default/V1/dna-payment/start-and-get')
                    .done(function (response) {
                        self.orderId(response)
                        self.pay()
                    }).fail(function (response) {
                        self.showError('Error: Fail loading order request')
                        throw 'Error: Fail loading order request';
                    }).always(function () {
                        fullScreenLoader.stopLoader(true);
                    })
            },
            pay() {
                const self = this;
                const { test_mode } = window.checkoutConfig.payment[this.getCode()];
                if(test_mode) {
                    window.activatePaymentTestMode();
                }
                window.authPaymentService(self.createAuthRequestData(), {
                    useRedirect: true
                }).then((result) => {
                    if(result.error) {
                        self.orderId(null);
                        self.showError("i18n: 'DNA Payment: Authorization request failed'")
                    }
                    window.openPaymentPage(self.createPaymentObject(result.value))
                });
            },
            createAuthRequestData: function() {
                const { terminal_id, client_id, client_secret } = window.checkoutConfig.payment[this.getCode()];
                return {
                    client_id: client_id,
                    client_secret: client_secret,
                    terminal: terminal_id,
                    invoiceId: this.orderId(),
                    amount: this.getAmount(),
                    currency: this.getCurrency()
                };
            },
            createPaymentObject: function(auth) {
                const { terminal_id, gateway_order_description, confirm_link, close_link, back_link, failure_back_link } = window.checkoutConfig.payment[this.getCode()];
                const { accountCountry, accountCity, accountStreet1, accountFirstName, accountLastName, accountPostalCode } = this.getAddressInfo();
                return {
                    terminal: terminal_id,
                    invoiceId: this.orderId(),
                    amount: this.getAmount(),
                    currency: this.getCurrency(),
                    backLink: back_link ? back_link : window.checkoutConfig.defaultSuccessPageUrl,
                    failureBackLink: failure_back_link,
                    postLink: confirm_link,
                    failurePostLink: close_link,
                    // accountId: "uuid2", //: TODO add error page
                    language: "eng",
                    description: gateway_order_description,
                    accountCountry: accountCountry,
                    accountCity: accountCity,
                    accountStreet1: accountStreet1,
                    accountEmail: this.getEmail(),
                    accountFirstName: accountFirstName,
                    accountLastName: accountLastName,
                    accountPostalCode: accountPostalCode,
                    auth: auth
                };
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
            getCurrency: function() {
                const totals = quote.totals();
                return totals['base_currency_code'];
            },
            getAmount: function() {
                return this.grandTotalAmount;
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
