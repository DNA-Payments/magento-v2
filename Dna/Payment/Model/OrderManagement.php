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

/**
 * Guest payment information management model.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderManagement implements \Dna\Payment\Api\OrderManagementInterface
{
    private $orderRepository;
    protected $orderFactory;
    protected $checkoutSession;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        Session $checkoutSession
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
    }
    /**
     *
     * @return string
     */
    public function startAndGetOrder() {
        $order = $this->getOrderInfo();
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
        }
    }


    /**
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
    ) {
        $order = $this->orderRepository->get($invoiceId);
        if(Order::STATE_PENDING_PAYMENT === $order->getStatus()) {
            $this->setOrderStatus($invoiceId, $order::STATE_PROCESSING);
        }
        return;
    }

    /**
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
     * @param string $message
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
    ) {
        $order = $this->orderRepository->get($invoiceId);
        if(Order::STATE_PENDING_PAYMENT === $order->getStatus()) {
            $this->setOrderStatus($invoiceId, $order::STATE_CLOSED);
        }
        return;
    }

}
