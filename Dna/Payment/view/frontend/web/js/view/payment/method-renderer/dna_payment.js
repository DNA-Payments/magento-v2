/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'dnaPaymentApi',
        'Magento_Checkout/js/view/payment/default'
    ],
    function (
        $,
        dnaPaymentApi,
        Component
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Dna_Payment/payment/form',
                transactionResult: ''
            },

            placeOrder: function (...args) {
                const self = this;
                const { dnaPayments } = window;

                $.ajax({
                    type: "POST",
                    url: dnaPayments.Config().TokenAPIConfig.url,
                    data: self.createAuthRequestData(),
                    dataType: "json"
                }).then(
                    function(auth) {
                        alert('auth')
                    },
                    function() {
                        alert("Authorization request failed");


                        //pay({ token_type: "Bearer", access_token: "" });
                    }
                );
                console.log(...args, 'here44', window.checkoutConfig.payment, window.checkoutConfig)
            },

            initObservable: function () {

                this._super()
                    .observe([]);
                return this;
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
                return {
                    grant_type: "client_credentials",
                    scope: "payment",
                    client_id: client_id,
                    client_secret: client_secret,
                    terminal: terminal_id,
                    ...options,
                    invoiceID: '1111',
                    amount: 111,
                    currency: "11111",
                };
            }
        });
    }
);