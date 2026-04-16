/**
 * Mixin for Magento set-payment-information actions.
 *
 * Tracks in-flight payment-information requests so Place Order can wait
 * for checkout update completion and avoid quote deactivation races.
 */
define([
    'mage/utils/wrapper',
    'Dna_Payment/js/model/totals-request-gate'
], function (wrapper, totalsRequestGate) {
    'use strict';

    return function (action) {
        return wrapper.wrap(action, function (originalFn) {
            var args = Array.prototype.slice.call(arguments, 1);
            var result;
            var finished = false;

            function finish() {
                if (finished) {
                    return;
                }

                finished = true;
                totalsRequestGate.end();
            }

            totalsRequestGate.begin();

            try {
                result = originalFn.apply(this, args);
            } catch (e) {
                finish();
                throw e;
            }

            if (result && typeof result.always === 'function') {
                result.always(finish);
            } else if (result && typeof result.then === 'function') {
                result.then(finish, finish);
            } else {
                finish();
            }

            return result;
        });
    };
});
