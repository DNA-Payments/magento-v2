/*browser:true*/
/*global define*/

define([
    'jquery',
], function ($) {
    'use strict';

    return {
        fetchQuotePaymentData: function (quoteId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: '/rest/V1/dna-payment/get-quote-payment-data?quoteId=' + quoteId,
                    type: 'get',
                    success: function (res) {
                        const {paymentData, auth, isTestMode} = (function () {
                            if (Array.isArray(res)) {
                                const [p, a, t] = res
                                return {
                                    paymentData: p,
                                    auth: a,
                                    isTestMode: t,
                                }
                            }
                            return res || {}
                        })()
                        resolve({paymentData, auth, isTestMode});
                    },
                    error: function (err) {
                        reject(err);
                    }
                })
            })
        },
    };
});
