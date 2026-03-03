/*browser:true*/
/*global define*/

/**
 * RestoreQuote action — reactivates the cart (quote) after a payment failure.
 *
 * Usage:
 *   restoreQuoteAction();                        // fire-and-forget (session fallback)
 *   restoreQuoteAction('000000001');              // pass orderId explicitly
 *   restoreQuoteAction('000000001', callback);    // with callback
 *   restoreQuoteAction(callback);                 // callback only (session fallback)
 */
define([
    'mage/storage'
], function (storage) {
    'use strict';

    return function (orderIdOrCallback, callback) {
        var orderId = null;
        var cb = null;

        if (typeof orderIdOrCallback === 'function') {
            cb = orderIdOrCallback;
        } else if (typeof orderIdOrCallback === 'string') {
            orderId = orderIdOrCallback;
            cb = callback;
        }

        return storage.post(
            'rest/V1/dna-payment/restore-quote',
            JSON.stringify({ orderId: orderId })
        ).done(function () {
            if (typeof cb === 'function') {
                cb();
            }
        }).fail(function (err) {
            console.error('DNA Payments: Failed to restore quote', err);
            if (typeof cb === 'function') {
                cb();
            }
        });
    };
});
