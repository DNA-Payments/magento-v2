<?php

namespace Dna\Payment\Model;

use Magento\Sales\Model\Order;
use Magento\Framework\Exception\LocalizedException;

class Helpers
{

    /**
     * @return \Magento\Sales\Model\Order
     * @throws LocalizedException
     */
    public static function getOrderInfo($orderId)
    {
        $order = self::getObjectManager()->create('Magento\Sales\Model\OrderFactory')->create()->loadByIncrementId($orderId);
        if (empty($order->getId())) {
            throw new LocalizedException(__('Cannot find order with ID %1', $orderId));
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
            throw new LocalizedException(
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

    /**
     * DNA takes refunds and captures in the currency the customer paid in (the order currency),
     * while Magento passes payment methods amounts in the base currency.
     * A credit memo (full or partial) and a full-order amount use their exact order-currency totals;
     * any other amount is converted with the order's exchange rate.
     */
    public static function toOrderAmount($payment, $baseAmount)
    {
        $order = $payment->getOrder();
        $baseAmount = round((float) $baseAmount, 2);
        if ($order->getBaseCurrencyCode() === $order->getOrderCurrencyCode()) {
            return $baseAmount;
        }

        $creditmemo = $payment->getCreditmemo();
        if ($creditmemo && round((float) $creditmemo->getBaseGrandTotal(), 2) === $baseAmount) {
            return round((float) $creditmemo->getGrandTotal(), 2);
        }
        if (round((float) $order->getBaseGrandTotal(), 2) === $baseAmount) {
            return round((float) $order->getGrandTotal(), 2);
        }
        $rate = (float) $order->getBaseToOrderRate();
        if ($rate <= 0) {
            throw new LocalizedException(__(
                'Cannot convert the amount to %1 for order #%2: the order has no exchange rate.',
                $order->getOrderCurrencyCode(),
                $order->getIncrementId()
            ));
        }
        return round($baseAmount * $rate, 2);
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
