<?php

namespace Dna\Payment\Api;

interface OrderManagementInterface
{
    /**
    * Set start status and get order id
    * @return string
    **/
    public function startAndGetOrder();

    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $secure3D
     * @param string $reference
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @return void
     */
    public function confirmOrder(
        $invoiceId,
        $id = null,
        $amount = null,
        $currency = null,
        $accountId = null,
        $message = null,
        $secure3D = null,
        $reference = null,
        $signature = null,
        $errorCode = null,
        $success = null
    );

    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $secure3D
     * @param string $reference
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @return void
     */
    public function closeOrder(
        $invoiceId,
        $id = null,
        $amount = null,
        $currency = null,
        $accountId = null,
        $message = null,
        $secure3D = null,
        $reference = null,
        $signature = null,
        $errorCode = null,
        $success = null
    );
}
