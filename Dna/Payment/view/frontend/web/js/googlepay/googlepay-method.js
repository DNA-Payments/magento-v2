/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';

        let config = window.checkoutConfig.payment;
        let dnaPaymentType = 'dna_payment_googlepay';

        console.log('googlepay window.checkoutConfig', window.checkoutConfig);

        if (config[dnaPaymentType] && config[dnaPaymentType].isActive) {
            console.log('Google Pay integration type is active');
            rendererList.push(
                {
                    type: dnaPaymentType,
                    component: 'Dna_Payment/js/googlepay/method-renderer/googlepay'
                }
            )
        }

        return Component.extend({});
    }
);
