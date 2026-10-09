<?php

namespace Dna\Payment\Controller\Result;

use Dna\Payment\Helper\DnaLogger;
use Dna\Payment\Model\TransactionStatus;
use Magento\Catalog\Controller\Product\View\ViewInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Dna\Payment\Gateway\Config\Config;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;


class Failure extends Action implements ViewInterface
{
    /**
     * @var Context
     */
    protected $_context;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    protected $orderRepository;
    protected $config;
    protected $messageManager;
    protected $dnaLogger;
    protected $transactionStatus;

    /**
     * Failure constructor.
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Config $config,
        Context $context,
        ManagerInterface $messageManager,
        ScopeConfigInterface $scopeConfig,
        OrderRepositoryInterface $orderRepository,
        DnaLogger $dnaLogger,
        TransactionStatus $transactionStatus
    )
    {
        $this->_context = $context;
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->_scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
        $this->dnaLogger = $dnaLogger;
        $this->transactionStatus = $transactionStatus;
        parent::__construct($context);

    }

    /**
     * @return ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
        $_quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory');

        $order = $_checkoutSession->getLastRealOrder();
        $status = $order->getStatus();

        // Never reopen the cart of a paid order, or of an order whose payment result is not known yet.
        if ($order->getId() && $order->getState() !== $order::STATE_CANCELED) {
            $dnaStatus = $order->getState() === $order::STATE_PENDING_PAYMENT
                ? $this->transactionStatus->check($order)
                : TransactionStatus::PAID;

            if ($dnaStatus === TransactionStatus::PAID) {
                $this->dnaLogger->info('Failure controller kept paid order', [
                    'order_id' => $order->getIncrementId(),
                    'state' => $order->getState(),
                ]);
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            }
            if ($dnaStatus === TransactionStatus::UNKNOWN) {
                $this->dnaLogger->info('Failure controller kept order with unconfirmed payment', [
                    'order_id' => $order->getIncrementId(),
                ]);
                $this->messageManager->addNoticeMessage(__(
                    'We are still confirming the payment for order #%1. Please check the order status before paying again.',
                    $order->getIncrementId()
                ));
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        }

        if ($order->getStatus() == $order::STATE_PENDING_PAYMENT) {
            try {
                $order->cancel();
                $this->orderRepository->save($order);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Error can not set status %1', $status));
            }
        }

        $quote = $_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
        if ($quote->getId()) {
            if (!$quote->getIsActive()) {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $this->dnaLogger->info('Failure controller restored quote', [
                    'order_id' => $order->getIncrementId(),
                    'quote_id' => $quote->getId(),
                ]);
            }

            $_checkoutSession->replaceQuote($quote);

            if (empty($this->getRequest()->getParam('cancel'))) {
                $this->messageManager->addErrorMessage(__('Sorry, we were unable to process your payment. Please try again.'));
            }

            $resultRedirect = $this->resultRedirectFactory->create();
            if ($this->getRequest()->getParam('cancel')) {
                $resultRedirect->setUrl($this->_url->getUrl('checkout') . '#payment');
            } else {
                $resultRedirect->setPath('checkout/cart');
            }

            return $resultRedirect;
        }

        $this->_view->loadLayout(['default', 'dna_failure']);
        $this->_view->renderLayout();
    }
}
