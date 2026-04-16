/**
 * Mixin for Magento_Checkout/js/model/error-processor
 *
 * Suppresses known transient checkout errors that can happen around
 * quote deactivation/restore races.
 *
 * Suppression is based on error semantics. If request URL is available,
 * it is narrowed to checkout cart endpoints.
 */
define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';

    function getErrorMessage(response) {
        if (response && response.responseJSON && response.responseJSON.message) {
            return response.responseJSON.message;
        }

        if (response && response.responseText) {
            try {
                return JSON.parse(response.responseText).message || '';
            } catch (e) {
                return '';
            }
        }

        if (response && response.message) {
            return response.message;
        }

        return '';
    }

    function getRequestUrl(response) {
        if (response && response.responseURL) {
            return response.responseURL;
        }

        if (response && response.url) {
            return response.url;
        }

        if (response && response.responseJSON && response.responseJSON.request_url) {
            return response.responseJSON.request_url;
        }

        return '';
    }

    function isCheckoutCartRequest(url) {
        var normalized = (url || '').toLowerCase();

        if (!normalized) {
            return true;
        }

        if (normalized.indexOf('/carts/mine/totals') !== -1) {
            return true;
        }

        if (normalized.indexOf('/carts/mine/payment-information') !== -1) {
            return true;
        }

        if (normalized.indexOf('/guest-carts/') !== -1 && normalized.indexOf('/totals') !== -1) {
            return true;
        }

        return normalized.indexOf('/guest-carts/') !== -1 && normalized.indexOf('/payment-information') !== -1;
    }

    function isTransientCartError(response) {
        var errorMsg = getErrorMessage(response);

        if (!errorMsg) {
            return false;
        }

        var normalizedMsg = errorMsg.toLowerCase();
        var isActiveCartError = normalizedMsg.indexOf('current customer does not have an active cart') !== -1 ||
            normalizedMsg.indexOf('active cart') !== -1;
        var isSelfAuthorizationError =
            (normalizedMsg.indexOf("isn't authorized to access") !== -1 || normalizedMsg.indexOf('not authorized to access') !== -1) &&
            normalizedMsg.indexOf('self') !== -1;

        if (!isActiveCartError && !isSelfAuthorizationError) {
            return false;
        }

        return isCheckoutCartRequest(getRequestUrl(response));
    }

    return function (errorProcessor) {
        errorProcessor.process = wrapper.wrap(
            errorProcessor.process,
            function (originalFn, response, messageContainer) {
                if (isTransientCartError(response)) {
                    return;
                }

                return originalFn(response, messageContainer);
            }
        );

        return errorProcessor;
    };
});
