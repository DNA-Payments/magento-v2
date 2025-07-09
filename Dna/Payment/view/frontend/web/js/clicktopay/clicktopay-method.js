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
        let dnaPaymentType = 'dna_payment_clicktopay';

        console.log('clicktopay', config);

        if (config[dnaPaymentType] && config[dnaPaymentType].isActive) {
            rendererList.push(
                {
                    type: dnaPaymentType,
                    component: 'Dna_Payment/js/clicktopay/method-renderer/clicktopay'
                }
            )
        }

        return Component.extend({});
    }
);
