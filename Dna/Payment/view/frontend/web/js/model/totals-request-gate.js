define([], function () {
    'use strict';

    var pendingCount = 0;
    var queue = [];

    function flushQueue() {
        var callbacks = queue.slice();
        queue = [];

        for (var i = 0; i < callbacks.length; i++) {
            try {
                callbacks[i]();
            } catch (e) {
                // Keep queue processing resilient.
            }
        }
    }

    function syncGlobalFlag() {
        window.dnaPaymentTotalsInFlight = pendingCount > 0;
    }

    return {
        begin: function () {
            pendingCount += 1;
            syncGlobalFlag();
        },

        end: function () {
            if (pendingCount > 0) {
                pendingCount -= 1;
            }

            syncGlobalFlag();

            if (pendingCount === 0) {
                flushQueue();
            }
        },

        isBusy: function () {
            return pendingCount > 0;
        },

        runWhenIdle: function (callback) {
            if (typeof callback !== 'function') {
                return;
            }

            if (pendingCount === 0) {
                callback();
                return;
            }

            queue.push(callback);
        }
    };
});
