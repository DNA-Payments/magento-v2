<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dna\Payment\Model;

/**
 * Source model for available payment actions
 */
class PaymentActions implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return Config::getPaymentActions();
    }
}
