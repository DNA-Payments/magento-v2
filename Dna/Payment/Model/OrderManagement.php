<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Dna\Payment\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Dna\Payment\Gateway\Config\Config;
use Psr\Log\LoggerInterface;

/**
 * Guest payment information management model.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderManagement implements \Dna\Payment\Api\OrderManagementInterface
{
    protected $orderRepository;
    protected $orderFactory;
    protected $checkoutSession;
    protected $logger;
    protected $config;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->config = $config;
    }
    /**
     *
     * @return string
     */
    public function startAndGetOrder() {
        $order = $this->getOrderInfo();
        $this->setOrderStatus($order->getId(), Order::STATE_PENDING_PAYMENT);
        return $order->getId();
    }

    /**
     * @return \Magento\Sales\Model\Order
     * @throws Exception
     */
    protected function getOrderInfo()
    {
        $order = $this->orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
        if (empty($order->getId())) {
            throw new Exception(__('Error on create preference Basic Checkout - Exception on getOrderInfo'));
        }
        return $order;
    }

    public function setOrderStatus($orderId, $status){
        $order = $this->orderRepository->get($orderId);
        $order->setState($status);
        $order->setStatus($status);

        try {
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            throw new Exception(__('Error can not set status ' + $status));
        }
    }


    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $code
     * @param string $secure3D
     * @param string $reference
     * @return void
     */
    public function confirmOrder(
         $invoiceId,
         $id = null,
         $amount = null,
         $currency = null,
         $accountId = null,
         $message = null,
         $code = null,
         $secure3D = null,
         $reference = null
    ) {
        $order = $this->orderRepository->get($invoiceId);
        if(Order::STATE_PENDING_PAYMENT === $order->getStatus()) {
            $this->setOrderStatus($invoiceId, $this->config->getOrderSuccessStatus());
            $orderPayment = $order->getPayment();
            $orderPayment->setAdditionalInformation( 'paymentResponse', [
                'id' => $id,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $message
            ]);
            $orderPayment->save();
        }
        return json_encode($orderPayment->getAdditionalInformation( "paymentResponse"));

    }

    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $code
     * @param string $secure3D
     * @param string $reference
     * @return void
     */
    public function closeOrder(
         $invoiceId,
         $id = null,
         $amount = null,
         $currency = null,
         $accountId = null,
         $message = null,
         $code = null,
         $secure3D = null,
         $reference = null
    ) {
        $order = $this->orderRepository->get($invoiceId);
        if(Order::STATE_PENDING_PAYMENT === $order->getStatus()) {
            $this->setOrderStatus($invoiceId, $order::STATE_CLOSED);
            $orderPayment = $order->getPayment();
            $orderPayment->setAdditionalInformation( "paymentResponse", [
                'id' => $id,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $message
            ]);
            $orderPayment->save();
        }
        return $order;
    }

}
