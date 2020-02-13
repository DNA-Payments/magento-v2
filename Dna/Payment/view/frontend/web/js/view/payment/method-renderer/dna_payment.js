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
        'Magento_Checkout/js/view/payment/default',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/place-order'
    ],
    function (
        ko,
        $,
        Component,
        globalMessageList,
        quote,
        placeOrderAction
    ) {
        'use strict';

        return Component.extend({
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            defaults: {
                template: 'Dna_Payment/payment/form',
                scriptLoaded: false,
                orderId: null,
            },
            placeOrder: function (args) {
                const self = this;
                this.makeOrder(() => {
                    if(self.scriptLoaded()) {
                        self.makeAuth();
                    } else {
                        self.loadScript(() => {
                            self.makeAuth();
                        })
                    }
                })
            },
            initObservable: function () {

                this._super()
                    .observe([
                        'scriptLoaded',
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
            makeOrder(cb) {
                const self = this;
                if (this.validate() &&
                    this.isPlaceOrderActionAllowed() === true
                ) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .done(
                            function (orderId) {
                                self.orderId(orderId);
                                cb();
                            }
                        ).fail(function(data){
                            self.orderId(null);
                            self.showError('Could\'t find order')
                        }).always(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        );

                    return true;
                }
            },
            makeAuth() {
                const self = this;
                const { dnaPayments } = window;
                console.log(self.createAuthRequestData())
                return;
                $.ajax({
                    type: "POST",
                    url: dnaPayments.Config().TokenAPIConfig.url,
                    data: self.createAuthRequestData(),
                    dataType: "json"
                }).then((auth) => {
                        dnaPayments.pay(this.createPaymentObject(auth))
                    },
                    function() {
                        self.showError("i18n: 'Authorization request failed'")
                    }
                );
            },
            createAuthRequestData: function(options = {}) {
                const { terminal_id, client_id, client_secret } = window.checkoutConfig.payment[this.getCode()];
                return {
                    grant_type: "client_credentials",
                    scope: "payment",
                    client_id: client_id,
                    client_secret: client_secret,
                    terminal: terminal_id,
                    invoiceID: this.orderId(),
                    amount: this.getAmount(),
                    currency: this.getCurrency(),
                    ...options
                };
            },
            loadScript: function (cb) {
                const self = this,
                    state = self.scriptLoaded;

                $('body').trigger('processStart');
                require([
                    ...self.getScriptUrl()
                ], function () {
                    state(true);
                    $('body').trigger('processStop');
                    cb();
                });
            },
            createPaymentObject: function(auth) {
                const { terminal_id, description } = window.checkoutConfig.payment[this.getCode()];
                const { accountCountry, accountCity, street1, accountFirstName, accountLastName, accountPostalCode } = this.getAddressInfo();
                return {
                    terminal: terminal_id,
                    invoiceId: this.orderId(),
                    amount: this.getAmount(),
                    currency: this.getCurrency(),
                    backLink: window.checkoutConfig.defaultSuccessPageUrl,
                    failureBackLink: "https://www.parkway-media.co.uk/",
                    postLink: window.checkoutConfig.defaultSuccessPageUrl,
                    failurePostLink: "https://www.parkway-media.co.uk/",
                    accountId: "uuid2",
                    language: "eng",
                    description: description,
                    accountCountry: accountCountry,
                    accountCity: accountCity,
                    accountStreet1: street1,
                    accountEmail: this.getEmail(),
                    accountFirstName: accountFirstName,
                    accountLastName: accountLastName,
                    accountPostalCode: accountPostalCode,
                    auth: auth
                };
            },
            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData())
                );
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
                    street1: address.street && Array.isArray(address.street) ? address.street.join(' ') : '',
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
            getScriptUrl() {
                const payment = window.checkoutConfig.payment[this.getCode()];
                return [
                    payment.test_mode
                        ? payment.js_url_test
                        : 'https://pay.dnapayments.com/payment-api.js'
                ];
            },
            validate() {
                const { accountCountry, accountCity, accountStreet1, accountEmail, accountFirstName, accountLastName, accountPostalCode } = this.createPaymentObject();
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
                console.log(errorMessage)
                globalMessageList.addErrorMessage({
                    message: errorMessage
                });
            }
        });
    }
);