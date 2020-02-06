/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/quote'

    ],
    function (
        $,
        Component,
        globalMessageList,
        quote
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Dna_Payment/payment/form',
                transactionResult: '',
                scriptLoaded: false
            },

            placeOrder: function (...args) {
                const self = this;
                console.log(self.scriptLoaded(), window.checkoutConfig.payment[this.getCode()]);
                if(self.scriptLoaded()) {
                    self.makeAuth();
                } else {
                    self.loadScript(() => {
                        self.makeAuth();
                    })
                }
            },
            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult',
                        'scriptLoaded'
                    ]);


                this.grandTotalAmount = quote.totals()['base_grand_total'];

                quote.totals.subscribe(function () {
                    if (self.grandTotalAmount !== quote.totals()['base_grand_total']) {
                        self.grandTotalAmount = quote.totals()['base_grand_total'];
                    }
                });

                return this;
            },
            makeAuth() {
                const self = this;
                const { dnaPayments } = window;

                $.ajax({
                    type: "POST",
                    url: dnaPayments.Config().TokenAPIConfig.url,
                    data: self.createAuthRequestData(),
                    dataType: "json"
                }).then((auth) => {
                        alert('auth');
                        return;
                        dnaPayments.pay(this.createPaymentObject(auth))
                    },
                    function() {
                        self.showError("i18n: 'Authorization request failed'")
                    }
                );
            },
            getCode: function() {
                return 'dna_payment';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'transaction_result': this.transactionResult()
                    }
                };
            },

            getTransactionResults: function() {
                return _.map(window.checkoutConfig.payment.dna_payment.transactionResults, function(value, key) {
                    return {
                        'value': key,
                        'transaction_result': value
                    }
                });
            },

            createAuthRequestData: function(options = {}) {
                const { terminal_id, client_id, client_secret } = window.checkoutConfig.payment[this.getCode()];
                const { entity_id } = window.checkoutConfig.quoteData;
                return {
                    grant_type: "client_credentials",
                    scope: "payment",
                    client_id: client_id,
                    client_secret: client_secret,
                    terminal: terminal_id,
                    invoiceID: entity_id,
                    amount: this.getAmount(),
                    currency: this.getCurrency(),
                    ...options
                };
            },
            createPaymentObject: function(auth) {
                const { terminal_id, description } = window.checkoutConfig.payment[this.getCode()];
                const { entity_id } = window.checkoutConfig.quoteData;
                const { accountCountry, accountCity, street1, accountFirstName, accountLastName, accountPostalCode } = this.getAddressInfo();
                return {
                    terminal: terminal_id,
                    invoiceId: entity_id,
                    amount: this.getAmount(),
                    currency: this.getCurrency(),
                    backLink: "https://www.parkway-media.co.uk/",
                    failureBackLink: "https://www.parkway-media.co.uk/",
                    postLink: "https://pay.dnapayments.com",
                    failurePostLink: "https://www.parkway-media.co.uk/",
                    accountId: "uuid2",
                    language: "eng",
                    description: description,
                    accountCountry: accountCountry, //account-holder.address.country ISO 3166-1 alpha-2 country code (max.length 2)
                    accountCity: accountCity, //max.length 50
                    accountStreet1: street1, //max.length 50
                    accountEmail: self.getEmail(), //max.length 256
                    accountFirstName: accountFirstName, //max.length 32
                    accountLastName: accountLastName, //max.length 32
                    accountPostalCode: accountPostalCode, //max.length 13
                    auth: auth
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
                    accountEmail: self.getEmail(),
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
            showError: function (errorMessage) {
                globalMessageList.addErrorMessage({
                    message: errorMessage
                });
            }
        });
    }
);