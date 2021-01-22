<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Dna\Payment\Model;

use Dna\Payment\Gateway\Config\Config;
use DNAPayments\DNAPayments;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Setup\Exception;
use Magento\Store\Model\StoreManagerInterface;
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
    protected $session;
    protected $storeManager;
    protected $urlBuilder;
    protected $isTestMode;
    protected $dnaPayment;
    protected $storeId;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        LoggerInterface $logger,
        Config $config,
        SessionManagerInterface $session,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->config = $config;
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->storeId = $this->session->getStoreId();
        $this->isTestMode = $this->config->getTestMode($this->storeId);
        $this->dnaPayment = new DNAPayments(
            [
                'isTestMode' => $this->isTestMode,
                'scopes' => [
                    'allowHosted' => true
                ]
            ]
        );
    }

    /**
     *
     * @return string
     * @throws Error
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function startAndGetOrder()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        $address = $order->getBillingAddress();

        $result = $this->dnaPayment->auth([
            'client_id' => $this->isTestMode ? $this->config->getClientIdTest($this->storeId) : $this->config->getClientId($this->storeId),
            'client_secret' => $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId),
            'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
            'invoiceId' => $order->getIncrementId(),
            'currency' => $order->getOrderCurrencyCode(),
            'amount' => $order->getBaseGrandTotal()
        ]);

        return $this->dnaPayment->generateUrl([
                'postLink' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/confirm'),
                'failurePostLink' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/failure'),
                'backLink' => $this->config->getBackLink($this->storeId) ? $this->urlBuilder->getUrl($this->config->getBackLink($this->storeId)) : $this->urlBuilder->getUrl('checkout/onepage/success'),
                'failureBackLink' => $this->config->getFailureBackLink($this->storeId) ? $this->urlBuilder->getUrl($this->config->getFailureBackLink($this->storeId)) : $this->urlBuilder->getUrl('dna/result/failure'),
                'description' => $this->config->getGatewayOrderDescription($this->storeId),
                'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
                'invoiceId' => $order->getIncrementId(),
                'currency' => $order->getOrderCurrencyCode(),
                'amount' => $order->getBaseGrandTotal(),
                'accountId' => $this->checkoutSession->getCustomer() ? $this->checkoutSession->getCustomer()->getId() : '',
                'accountCountry' => $address->getCountryId(),
                'accountCity' => $address->getCity(),
                'accountStreet1' => join(" ", $address->getStreet()),
                'accountEmail' => $address->getEmail(),
                'accountFirstName' => $address->getFirstname(),
                'accountLastName' => $address->getLastname(),
                'accountPostalCode' => $address->getPostcode()
        ], $result);
    }

    /**
     * @return \Magento\Sales\Model\Order
     * @throws \Error
     */
    protected function getOrderInfo($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (empty($order->getId())) {
            throw new Error(__('Error: Can not find order'));
        }
        return $order;
    }

    public function setOrderStatus($orderId, $status)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        $order->setState($status);
        $order->setStatus($status);

        try {
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            throw new Error(__('Error can not set status ' + $status));
        }
    }

    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $secure3D
     * @param string $rrn
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @return void
     * @throws Exception
     */
    public function confirmOrder(
        $invoiceId,
        $id = null,
        $amount = null,
        $currency = null,
        $accountId = null,
        $message = null,
        $secure3D = null,
        $rrn = null,
        $signature = null,
        $errorCode = null,
        $success = null
    ) {
        $order = $this->getOrderInfo($invoiceId);
        $orderPayment = $order->getPayment();
        $secret = $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId);
        if ($this->dnaPayment->isValidSignature([
            'id' => $id,
            'amount' => $amount,
            'currency' => $currency,
            'invoiceId' => $invoiceId,
            'errorCode' => $errorCode,
            'success' => $success,
            'signature' => $signature
        ], $secret) && $success) {
            try {
                $this->setOrderStatus($invoiceId, $this->config->getOrderSuccessStatus());
                $orderPayment
                    ->setTransactionId($id)
                    ->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true)
                    ->setAdditionalInformation("paymentResponse", [
                        'id' => $id,
                        'rrn' => $rrn,
                        'message' => $message
                    ])
                    ->save();
                $order
                    ->addStatusHistoryComment("Your payment with DNA Payment is complete. Transaction #$id")
                    ->setIsCustomerNotified(true)
                    ->save();
                $this->sendEmail($invoiceId);
            } catch (\Magento\Checkout\Exception $e) {
                $this->logger->error($e);
            }
        }
        return $invoiceId;
    }

    /**
     * Send email about new order.
     * Process mail exception
     *
     * @param string $orderId
     * @return bool
     */
    public function sendEmail($orderId)
    {
        try {
            $order = $this->getOrderInfo($orderId);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $emailSender = $objectManager->create('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
            $emailSender->send($order, true);
        } catch (\Magento\Framework\Mail\Exception $exception) {
            $this->logger->info($exception);
            return false;
        }
        return true;
    }

    /**
     * @param string $invoiceId
     * @param string $id
     * @param string $amount
     * @param string $currency
     * @param string $accountId
     * @param string $message
     * @param string $secure3D
     * @param string $rrn
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @return void
     * @throws Exception
     */
    public function failureOrder(
        $invoiceId,
        $id = null,
        $amount = null,
        $currency = null,
        $accountId = null,
        $message = null,
        $secure3D = null,
        $rrn = null,
        $signature = null,
        $errorCode = null,
        $success = null
    ) {
        $order = $this->getOrderInfo($invoiceId);
        $orderPayment = $order->getPayment();
        $secret = $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId);
        if ($this->dnaPayment->isValidSignature([
            'id' => $id,
            'amount' => $amount,
            'currency' => $currency,
            'invoiceId' => $invoiceId,
            'errorCode' => $errorCode,
            'success' => $success,
            'signature' => $signature
        ], $secret)) {
            $order->addStatusHistoryComment("Your payment with DNA Payment is failed. Transaction #$id");
            $orderPayment
                ->setTransactionId($id)
                ->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true)
                ->setAdditionalInformation('paymentResponse', [
                    'id' => $id,
                    'rrn' => $rrn,
                    'message' => $message
                ])
                ->save();
            $this->setOrderStatus($invoiceId, $order::STATE_CLOSED);

        }
        return $invoiceId;
    }
}
