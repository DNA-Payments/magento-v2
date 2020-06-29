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
use Dna\Payment\Model\DNAPaymentApi;
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
        $DNAPaymentApiInstance = new DNAPaymentApi();
        $order = $this->getOrderInfo($this->checkoutSession->getLastRealOrderId());
        $this->setOrderStatus($order->getId(), Order::STATE_PENDING_PAYMENT);

        $DNAPaymentApiInstance->auth((object) array(
            'invoiceId' => $order->getIncrementId(),
            'currency' => $order->getOrderCurrencyCode(),
            'amount' => $order->getBaseGrandTotal()
        ));

        return ;

    }

    /**
     * @return \Magento\Sales\Model\Order
     * @throws Exception
     */
    protected function getOrderInfo($incrementId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (empty($order->getId())) {
            throw new Exception(__('Error: Can not find order by increment id'));
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
        $orderByIncrement = $this->getOrderInfo($invoiceId);
        $order = $this->orderRepository->get($orderByIncrement->getId());
        $orderPayment = $order->getPayment();
        if(Order::STATE_PENDING_PAYMENT === $order->getStatus()) {
            $this->setOrderStatus($orderByIncrement->getId(), $this->config->getOrderSuccessStatus());
            $orderPayment->setAdditionalInformation( "paymentResponse", [
                'id' => $id,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $message
            ]);
            $orderPayment->save();
        }
        return $invoiceId;

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
        $orderByIncrement = $this->getOrderInfo($invoiceId);
        $order = $this->orderRepository->get($orderByIncrement->getId());
        $orderPayment = $order->getPayment();
        if(Order::STATE_PENDING_PAYMENT === $order->getStatus()) {
            $this->setOrderStatus($orderByIncrement->getId(), $order::STATE_CLOSED);
            $orderPayment->setAdditionalInformation( "paymentResponse", [
                'id' => $id,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $message
            ]);
            $orderPayment->save();
        }
        return $invoiceId;
    }

}
