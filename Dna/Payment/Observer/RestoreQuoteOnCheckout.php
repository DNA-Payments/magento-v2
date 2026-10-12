<?php

namespace Dna\Payment\Observer;

use Dna\Payment\Helper\DnaLogger;
use Dna\Payment\Model\TransactionStatus;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Restores the customer's quote on checkout page load if there is a
 * pending-payment DNA order and the cart has been deactivated.
 *
 * Covers edge cases:
 *  - Hosted-fields decline + page refresh
 *  - Full-redirect payment page closed/abandoned by user
 */
class RestoreQuoteOnCheckout implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var DnaLogger
     */
    private $dnaLogger;

    /**
     * @var TransactionStatus
     */
    private $transactionStatus;

    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        OrderRepositoryInterface $orderRepository,
        OrderCollectionFactory $orderCollectionFactory,
        DnaLogger $dnaLogger,
        TransactionStatus $transactionStatus
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->dnaLogger = $dnaLogger;
        $this->transactionStatus = $transactionStatus;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                return;
            }

            // Only restore for DNA payment methods with pending_payment status
            $paymentMethod = $order->getPayment() ? $order->getPayment()->getMethod() : '';
            if (strpos($paymentMethod, 'dna_payment') === false) {
                return;
            }

            if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
                return;
            }

            $quoteId = $order->getQuoteId();
            if (!$quoteId) {
                return;
            }

            // Don't overwrite a newer active cart that has items
            $currentQuote = $this->checkoutSession->getQuote();
            if ($currentQuote
            && $currentQuote->getId()
            && $currentQuote->getIsActive()
            && $currentQuote->getItemsCount() > 0
            && $currentQuote->getId() != $quoteId
            ) {
                return;
            }

            // Cancel ALL pending_payment DNA orders for this quote
            $pendingOrders = $this->orderCollectionFactory->create()
                ->addFieldToFilter('quote_id', $quoteId)
                ->addFieldToFilter('state', Order::STATE_PENDING_PAYMENT);

            $keepQuote = false;
            foreach ($pendingOrders as $pendingOrder) {
                $pendingPaymentMethod = $pendingOrder->getPayment() ? $pendingOrder->getPayment()->getMethod() : "";
                if (strpos($pendingPaymentMethod, "dna_payment") === false) {
                    continue;
                }

                // The payment may already be approved in DNA while its callback is still on the way.
                $status = $this->transactionStatus->check($pendingOrder);
                if ($status === TransactionStatus::PAID || $status === TransactionStatus::UNKNOWN) {
                    $keepQuote = true;
                    $this->dnaLogger->info('RestoreQuoteOnCheckout kept pending_payment order', [
                        'order_id' => $pendingOrder->getIncrementId(),
                        'dna_status' => $status,
                    ]);
                    continue;
                }

                if ($pendingOrder->canCancel()) {
                    $pendingOrder->cancel();
                    $this->orderRepository->save($pendingOrder);
                    $this->dnaLogger->info('RestoreQuoteOnCheckout canceled pending_payment order', [
                        'order_id' => $pendingOrder->getIncrementId(),
                    ]);
                }
            }

            if ($keepQuote) {
                return;
            }

            // Restore the quote
            $quote = $this->cartRepository->get($quoteId);
            if ($quote->getId() && !$quote->getIsActive()) {
                $quote->setIsActive(1)->setReservedOrderId(null);
                $this->cartRepository->save($quote);
                $this->checkoutSession->replaceQuote($quote);
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->dnaLogger->info('RestoreQuoteOnCheckout restored quote', [
                    'order_id' => $order->getIncrementId(),
                    'quote_id' => $quote->getId(),
                ]);
            }
        }
        catch (\Exception $e) {
            $this->dnaLogger->logException('RestoreQuoteOnCheckout failed', $e);
        }
    }
}
