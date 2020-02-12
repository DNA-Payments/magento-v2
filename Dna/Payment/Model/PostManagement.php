<?php
namespace Dna\Payment\Model;
use Magento\Sales\Api\OrderRepositoryInterface;

class PostManagement {

    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    public function getPost(){
        $orderId = 25;
        $order = $this->orderRepository->get($orderId);
        $order->setState(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);
        $order->setStatus(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);

        try {
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->error($e);
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }
    }
}