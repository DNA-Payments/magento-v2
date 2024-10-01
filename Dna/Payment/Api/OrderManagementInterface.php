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
    * Cancel order
    * @param string $orderId
    * @return void
    **/
    public function cancelOrder($orderId);

    /**
    * Get dumb auth data for validating
    * @return object
    **/
    public function getDnaDumbAuthData();

    /**
    * @param string $orderId
    * @return void
    **/
    public function getDnaPaymentData($orderId);

    /**
     * @param string $orderId
     * @return void
     **/
    public function getOrderPaymentData($orderId);

    /**
     * @param string $quoteId
     * @return void
     */
    public function getQuotePaymentData($quoteId);

    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $secure3D
     * @param string $rrn
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @param boolean $settled
     * @param string $paymentMethod
     * @param string $paypalCaptureStatus
     * @param string $paypalCaptureStatusReason
     * @param string $paypalOrderStatus
     * @param string $cardTokenId
     * @param string $cardExpiryDate
     * @param string $cardSchemeId
     * @param string $cardSchemeName
     * @param string $cardPanStarred
     * @param string $storeCardOnFile
     * @param string $cardholderName
     * @param string $merchantCustomData
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
        $rrn = null,
        $signature = null,
        $errorCode = null,
        $success = null,
        $settled = null,
        $paymentMethod = null,
        $paypalCaptureStatus = null,
        $paypalCaptureStatusReason = null,
        $paypalOrderStatus = null,
        $cardTokenId = null,
        $cardExpiryDate = null,
        $cardSchemeId = null,
        $cardSchemeName = null,
        $cardPanStarred = null,
        $storeCardOnFile = null,
        $cardholderName = null,
        $merchantCustomData = null
    );
    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $secure3D
     * @param string $rrn
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @param boolean $settled
     * @param string $paymentMethod
     * @param string $paypalCaptureStatus
     * @param string $paypalCaptureStatusReason
     * @param string $paypalOrderStatus
     * @return void
     */
    public function failureOrder(
        $invoiceId,
        $id = null,
        $amount = null,
        $currency = null,
        $accountId = null,
        $message = null,
        $secure3D = null,
        $rrn = null,
        $signature = null,
        $errorCode = null,
        $success = null,
        $settled = null,
        $paymentMethod = null,
        $paypalCaptureStatus = null,
        $paypalCaptureStatusReason = null,
        $paypalOrderStatus = null
    );
}
