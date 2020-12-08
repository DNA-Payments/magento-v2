<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dna\Payment\Gateway\Request;

use Dna\Payment\Gateway\Config\Config;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class InitializationRequest implements BuilderInterface
{
    private $_gatewayConfig;
    private $_logger;
    private $_session;

    /**
     * @param Config $gatewayConfig
     * @param LoggerInterface $logger
     * @param Session $session
     */
    public function __construct(
        Config $gatewayConfig,
        LoggerInterface $logger,
        Session $session
    ) {
        $this->_gatewayConfig = $gatewayConfig;
        $this->_logger = $logger;
        $this->_session = $session;
    }

    /**
     * Checks the quote for validity
     * @param OrderAdapter $order
     * @return bool;
     */
    private function validateQuote(OrderAdapter $order)
    {
        return true;
    }

    /**
     * Builds ENV request
     * From: https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/Adapter.php
     * The $buildSubject contains:
     * 'payment' => $this->getInfoInstance()
     * 'paymentAction' => $paymentAction
     * 'stateObject' => $stateObject
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $payment = $buildSubject['payment'];
        $stateObject = $buildSubject['stateObject'];

        $order = $payment->getOrder();

        if ($this->validateQuote($order)) {
            $stateObject->setIsCustomerNotified(false);
            $stateObject->setState(Order::STATE_PENDING_PAYMENT);
            $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
        } else {
            $stateObject->setIsCustomerNotified(false);
            $stateObject->setState(Order::STATE_CANCELED);
            $stateObject->setStatus(Order::STATE_CANCELED);
        }

        return [ 'IGNORED' => [ 'IGNORED' ] ];
    }
}
