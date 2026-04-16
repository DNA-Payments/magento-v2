const config = {
    paths: {
        'dnapayments-api': 'https://pay.dnapayments.com/checkout/payment-api',
        'dna-google-pay': 'https://pay.dnapayments.com/components/google-pay/google-pay-component',
        'dna-apple-pay': 'https://pay.dnapayments.com/components/apple-pay/apple-pay-component',
        'dna-alipay-wechat-pay': 'https://pay.dnapayments.com/components/alipay-wechat-pay/alipay-wechat-pay-component',
        'dna-click-to-pay': 'https://pay.dnapayments.com/components/click-to-pay/click-to-pay-component',
    },
    map: {
        '*': {
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/action/get-totals': {
                'Dna_Payment/js/model/get-totals-mixin': true
            },
            'Magento_Checkout/js/action/set-payment-information': {
                'Dna_Payment/js/model/set-payment-information-mixin': true
            },
            'Magento_Checkout/js/action/set-payment-information-extended': {
                'Dna_Payment/js/model/set-payment-information-mixin': true
            },
            'Magento_Checkout/js/model/error-processor': {
                'Dna_Payment/js/model/error-processor-mixin': true
            }
        }
    }
};
