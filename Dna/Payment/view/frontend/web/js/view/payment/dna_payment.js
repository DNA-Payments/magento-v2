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
        let dnaPaymentType = 'dna_payment';

        console.log('dna_payments config = ', config);
        console.log('dna_payments config = ', window.checkoutConfig);

        if (config[dnaPaymentType] && config[dnaPaymentType].isActive && config[dnaPaymentType].integrationType === '2') {
            console.log('Hosted Fields integration type');

            rendererList.push(
                {
                    type: dnaPaymentType,
                    component: 'Dna_Payment/js/view/payment/method-renderer/hosted-fields'
                }
            )
        } else {
            console.log('Full / Lightbox integration type');

            rendererList.push(
                {
                    type: dnaPaymentType,
                    component: 'Dna_Payment/js/view/payment/method-renderer/dna_payment'
                }
            );
        }

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
