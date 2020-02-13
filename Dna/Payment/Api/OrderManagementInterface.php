<?php
 /**
  * Copyright © Magento, Inc. All rights reserved.
  * See COPYING.txt for license details.
  */
 namespace Dna\Payment\Api;

 /**
  * Interface for managing guest payment information
  * @api
  * @since 100.0.2
  */
 interface OrderManagementInterface
 {
     /**
      * Set payment information and place order for a specified cart.
      *
      * @param string $cartId
      * @param string $email
      * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
      * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
      * @throws \Magento\Framework\Exception\CouldNotSaveException
      * @return int Order ID.
      */
     public function savePaymentInformationAndPlaceOrder(
         $cartId,
         $email,
         \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
         \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
     );

     /**
      * Set payment information for a specified cart.
      *
      * @param string $cartId
      * @param string $email
      * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
      * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
      * @throws \Magento\Framework\Exception\CouldNotSaveException
      * @return int Order ID.
      */
     public function savePaymentInformation(
         $cartId,
         $email,
         \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
         \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
     );

     /**
      * Get payment information
      *
      * @param string $cartId
      * @return \Magento\Checkout\Api\Data\PaymentDetailsInterface
      */
     public function getPaymentInformation($cartId);

     /**
      * Confirm payment order
      *
      * @param string $orderId
      * @param string $amount
      * @param string $currency
      * @param string $invoiceId
      * @param string $accountId
      * @param string $email
      * @param string $phone
      * @param string $description
      * @param string $reference
      * @param string $language
      * @param string $status
      * @param string $secure3D
      * @return void
      */
     public function confirmOrder(
         $invoiceId,
         $id = null,
         $amount = null,
         $currency = null,
         $accountId = null,
         $email = null,
         $phone = null,
         $description = null,
         $reference = null,
         $language = null,
         $status = null,
         $secure3D = null
     );

      /**
       * Confirm payment order
       *
       * @param string $orderId
       * @param string $amount
       * @param string $currency
       * @param string $invoiceId
       * @param string $accountId
       * @param string $email
       * @param string $phone
       * @param string $description
       * @param string $reference
       * @param string $language
       * @param string $status
       * @param string $error
       * @return void
       */
      public function closeOrder(
          $invoiceId,
          $id = null,
          $amount = null,
          $currency = null,
          $accountId = null,
          $email = null,
          $phone = null,
          $description = null,
          $reference = null,
          $language = null,
          $status = null,
          $secure3D = null
      );
 }