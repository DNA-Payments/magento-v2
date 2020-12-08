<?php

namespace Dna\Payment\Plugin;

use Dna\Payment\Gateway\Config\Config;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderSenderPlugin
{
    private $_gatewayConfig;
    private $_logger;

    /**
     * @param Config $gatewayConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $gatewayConfig,
        LoggerInterface $logger
    ) {
        $this->_gatewayConfig = $gatewayConfig;
        $this->_logger = $logger;
    }

    public function aroundSend(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $subject,
        callable $proceed,
        Order $order,
        $forceSyncMode = false
    ) {
        $payment = $order->getPayment()->getMethodInstance()->getCode();
        if ($payment === 'dna_payment' && $order->getState() === 'pending_payment') {
            return false;
        }

        return $proceed($order, $forceSyncMode);
    }
}
