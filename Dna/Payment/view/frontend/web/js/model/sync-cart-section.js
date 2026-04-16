define([
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data'
], function (quote, customerData) {
    'use strict';

    return function syncCartSection(optionalTotals) {
        var totals = optionalTotals || quote.totals();
        var cartData = customerData.get('cart')();
        var quoteItemsQty = totals && Number(totals.items_qty || 0);
        var cartSummaryCount = cartData && Number(cartData.summary_count || 0);

        if (quoteItemsQty > 0 && cartSummaryCount === 0) {
            customerData.reload(['cart'], true);
        }
    };
});
