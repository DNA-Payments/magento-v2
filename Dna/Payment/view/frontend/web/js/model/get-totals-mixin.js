/**
 * Mixin for Magento_Checkout/js/action/get-totals
 *
 * Tracks totals request in-flight state so placeOrder can wait
 * for completion instead of racing with quote deactivation.
 */
define([
    'mage/utils/wrapper',
    'Dna_Payment/js/model/totals-request-gate'
], function (wrapper, totalsRequestGate) {
    'use strict';

    return function (getTotalsAction) {
        return wrapper.wrap(getTotalsAction, function (originalFn, callbacks, deferred) {
            totalsRequestGate.begin();

            var finished = false;
            var result;
            var finish = function () {
                if (finished) {
                    return;
                }

                finished = true;
                totalsRequestGate.end();
            };

            try {
                result = originalFn(callbacks, deferred);
            } catch (e) {
                finish();
                throw e;
            }

            if (deferred && typeof deferred.always === 'function') {
                deferred.always(finish);
            } else if (result && typeof result.always === 'function') {
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
