<?php

namespace Dna\Payment\Model;

use Magento\Sales\Model\Order;

class Helpers
{

    /**
     * @return \Magento\Sales\Model\Order
     * @throws \Error
     */
    public static function getOrderInfo($orderId)
    {
        $order = self::getObjectManager()->create('Magento\Sales\Model\OrderFactory')->create()->loadByIncrementId($orderId);
        if (empty($order->getId())) {
            throw new Error(__('Error: Can not find order'));
        }
        return $order;
    }

    protected static function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }

    public static function invoiceOrder($order, $transactionId)
    {
        if (!$order->canInvoice()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot create an invoice.')
            );
        }

        $invoice = self::getObjectManager()
            ->create('Magento\Sales\Model\Service\InvoiceService')
            ->prepareInvoice($order);

        if (!$invoice->getTotalQty()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }

        $invoice->setTransactionId($transactionId);
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_ONLINE);
        $invoice->register();

        $transaction = self::getObjectManager()->create('Magento\Framework\DB\Transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
    }

    public static function isValidStatusPayPalStatus($paypalCaptureStatus)
    {
        if (empty($paypalCaptureStatus)) {
            return true;
        }

        return !(
            stripos($paypalCaptureStatus, 'PENDING') !== false ||
            stripos($paypalCaptureStatus, 'CUSTOMER.DISPUTE.CREATED') !== false ||
            stripos($paypalCaptureStatus, 'CUSTOMER.DISPUTE.UPDATED') !== false ||
            stripos($paypalCaptureStatus, 'RISK.DISPUTE.CREATED') !== false
        );
    }
}
