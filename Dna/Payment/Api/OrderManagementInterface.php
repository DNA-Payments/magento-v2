<?php

namespace Dna\Payment\Api;

interface OrderManagementInterface {

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
