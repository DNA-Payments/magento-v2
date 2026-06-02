/*browser:true*/
/*global define*/

define([
    'jquery',
], function ($) {
    'use strict';

    var quotePaymentDataRequests = {};
    var quotePaymentDataCache = {};

    return {
        clearCache: function () {
            quotePaymentDataCache = {};
            quotePaymentDataRequests = {};
        },
        fetchQuotePaymentData: function (quoteId, cacheKey) {
            cacheKey = cacheKey || String(quoteId || '');

            if (quotePaymentDataCache[cacheKey]) {
                return Promise.resolve(quotePaymentDataCache[cacheKey]);
            }

            if (quotePaymentDataRequests[cacheKey]) {
                return quotePaymentDataRequests[cacheKey];
            }

            quotePaymentDataRequests[cacheKey] = new Promise((resolve, reject) => {
                $.ajax({
                    url: '/rest/V1/dna-payment/get-quote-payment-data?quoteId=' + quoteId,
                    type: 'get',
                    success: function (res) {
                        const { paymentData, auth, isTestMode } = (function () {
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
                        quotePaymentDataCache[cacheKey] = { paymentData, auth, isTestMode };
                        resolve(quotePaymentDataCache[cacheKey]);
                    },
                    error: function (err) {
                        reject(err);
                    },
                    complete: function () {
                        delete quotePaymentDataRequests[cacheKey];
                    }
                })
            })

            return quotePaymentDataRequests[cacheKey];
        },
        fetchOrderPaymentData: function (orderId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: '/rest/V1/dna-payment/get-order-payment-data?orderId=' + orderId,
                    type: 'get',
                    success: function (res) {
                        const { paymentData, auth, isTestMode } = (function () {
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
                        resolve({ paymentData, auth, isTestMode });
                    },
                    error: function (err) {
                        reject(err);
                    }
                })
            })
        },
    };
});
