<?php

namespace Dna\Payment\Model;

use Magento\Paypal\Model\AbstractConfig;

class Config extends AbstractConfig
{
    const PAYMENT_CODE = 'dna_payment';

    const PAYMENT_PAYMENT_ACTION_SALE = 'authorize_capture';

    const PAYMENT_PAYMENT_ACTION_AUTH = 'authorize';

    const PAYMENT_PAYMENT_ACTION_DEFAULT = 'default';

    const ORDER_IS_FINISHED_PAYMENT_KEY = 'is_finished_payment';

    /**
     * {@inheritdoc}
     */
    public static function getPaymentActions()
    {
        return [
            self::PAYMENT_PAYMENT_ACTION_SALE => __('Sale'),
            self::PAYMENT_PAYMENT_ACTION_AUTH => __('Authorization'),
            self::PAYMENT_PAYMENT_ACTION_DEFAULT => __('Default'),
        ];
    }

    public static function getTransactionType($paymentAction)
    {
        switch ($paymentAction) {
            case self::PAYMENT_PAYMENT_ACTION_SALE:
                return 'SALE';
            case self::PAYMENT_PAYMENT_ACTION_AUTH:
                return 'AUTH';
            default:
                return $paymentAction;
        }
    }
}
