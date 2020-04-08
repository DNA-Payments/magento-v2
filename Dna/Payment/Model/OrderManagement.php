<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Dna\Payment\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Guest payment information management model.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderManagement implements \Dna\Payment\Api\OrderManagementInterface
{

    /**
     * @var \Magento\Quote\Api\GuestBillingAddressManagementInterface
     */
    protected $billingAddressManagement;

    /**
     * @var \Magento\Quote\Api\GuestPaymentMethodManagementInterface
     */
    protected $paymentMethodManagement;

    /**
     * @var \Magento\Quote\Api\GuestCartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var \Magento\Checkout\Api\PaymentInformationManagementInterface
     */
    protected $paymentInformationManagement;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var ResourceConnection
     */
    private $connectionPool;

    private $orderRepository;

    /**
     * @param \Magento\Quote\Api\GuestBillingAddressManagementInterface $billingAddressManagement
     * @param \Magento\Quote\Api\GuestPaymentMethodManagementInterface $paymentMethodManagement
     * @param \Magento\Quote\Api\GuestCartManagementInterface $cartManagement
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManagement
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param ResourceConnection $connectionPool
     * @codeCoverageIgnore
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        \Magento\Quote\Api\GuestBillingAddressManagementInterface $billingAddressManagement,
        \Magento\Quote\Api\GuestPaymentMethodManagementInterface $paymentMethodManagement,
        \Magento\Quote\Api\GuestCartManagementInterface $cartManagement,
        \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManagement,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        ResourceConnection $connectionPool = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->billingAddressManagement = $billingAddressManagement;
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->cartManagement = $cartManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->connectionPool = $connectionPool ?: ObjectManager::getInstance()->get(ResourceConnection::class);
    }

    /**
     * @inheritdoc
     */
    public function savePaymentInformationAndPlaceOrder(
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        $salesConnection = $this->connectionPool->getConnection('sales');
        $checkoutConnection = $this->connectionPool->getConnection('checkout');
        $salesConnection->beginTransaction();
        $checkoutConnection->beginTransaction();

        try {
            $this->savePaymentInformation($cartId, $email, $paymentMethod, $billingAddress);
            try {
                $orderId = $this->cartManagement->placeOrder($cartId);
                $this->setOrderStatus($orderId, Order::STATE_CLOSED);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                throw new CouldNotSaveException(
                    __($e->getMessage()),
                    $e
                );
            } catch (\Exception $e) {
                $this->getLogger()->critical($e);
                throw new CouldNotSaveException(
                    __('An error occurred on the server. Please try to place the order again.'),
                    $e
                );
            }
            $salesConnection->commit();
            $checkoutConnection->commit();
        } catch (\Exception $e) {
            $salesConnection->rollBack();
            $checkoutConnection->rollBack();
            throw $e;
        }

        return $orderId;
    }

    /**
     * @inheritdoc
     */
    public function savePaymentInformation(
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($quoteIdMask->getQuoteId());

        if ($billingAddress) {
            $billingAddress->setEmail($email);
            $quote->removeAddress($quote->getBillingAddress()->getId());
            $quote->setBillingAddress($billingAddress);
            $quote->setDataChanges(true);
        } else {
            $quote->getBillingAddress()->setEmail($email);
        }
        $this->limitShippingCarrier($quote);

        $this->paymentMethodManagement->set($cartId, $paymentMethod);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentInformation($cartId)
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        return $this->paymentInformationManagement->getPaymentInformation($quoteIdMask->getQuoteId());
    }

    /**
     * Get logger instance
     *
     * @return \Psr\Log\LoggerInterface
     * @deprecated 100.1.8
     */
    private function getLogger()
    {
        if (!$this->logger) {
            $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
        }
        return $this->logger;
    }

    /**
     * Limits shipping rates request by carrier from shipping address.
     *
     * @param Quote $quote
     *
     * @return void
     * @see \Magento\Shipping\Model\Shipping::collectRates
     */
    private function limitShippingCarrier(Quote $quote) : void
    {
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getShippingMethod()) {
            $shippingRate = $shippingAddress->getShippingRateByCode($shippingAddress->getShippingMethod());
            $shippingAddress->setLimitCarrier($shippingRate->getCarrier());
        }
    }

    public function setOrderStatus($orderId, $status){
        $order = $this->orderRepository->get($orderId);
        $order->setState($status);
        $order->setStatus($status);

        try {
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->error($e);
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
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
