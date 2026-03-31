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
    'mage/storage',
    'Magento_Customer/js/customer-data'
], function (storage, customerData) {
    'use strict';

    return function (orderIdOrCallback, callback) {
        var orderId = null;
        var cb = null;

        if (typeof orderIdOrCallback === 'function') {
            cb = orderIdOrCallback;
        } else {
            orderId = orderIdOrCallback || null;
            cb = callback;
        }

        customerData.invalidate(['cart']);

        return storage.post(
            'rest/V1/dna-payment/restore-quote',
            JSON.stringify({ orderId: orderId })
        ).always(function () {
            if (typeof cb === 'function') {
                cb();
            } else {
                customerData.reload(['cart'], true);
            }
        });
    };
});
