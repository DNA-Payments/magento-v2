<?php

namespace Dna\Payment\Controller\Result;

use Magento\Catalog\Controller\Product\View\ViewInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Dna\Payment\Gateway\Config\Config;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;


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
        OrderRepositoryInterface $orderRepository
    )
    {
        $this->_context = $context;
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->_scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
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
        $storeId = $_checkoutSession->getStoreId();

        $order = $_checkoutSession->getLastRealOrder();
        $status = $order->getStatus();

        if ($order->getStatus() == $order::STATE_PENDING_PAYMENT) {
            try {
                $order->cancel();
                $this->orderRepository->save($order);
            } catch (\Exception $e) {
                throw new Error(__('Error can not set status ' + $status));
            }
        }

        $quote = $_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
        if ($quote->getId()) {
            $quote->setIsActive(1)->setReservedOrderId(null)->save();
            $_checkoutSession->replaceQuote($quote);

            if (empty($this->getRequest()->getParam('cancel'))) {
                $this->messageManager->addErrorMessage(__('An error occurred in the process of payment'));
            }

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        }
    }
}
