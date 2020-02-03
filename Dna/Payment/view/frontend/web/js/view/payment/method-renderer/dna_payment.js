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
                console.log(...args, 'here44', window.checkoutConfig.payment, window.checkoutConfig.order)
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);
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
            }
        });
    }
);