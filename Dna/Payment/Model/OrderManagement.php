<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Dna\Payment\Model;

use Dna\Payment\Gateway\Config\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Setup\Exception;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\UrlInterface;

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
        $this->dnaPayment = new \DNAPayments\DNAPayments(
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
     */
    public function startAndGetOrder()
    {

        $order = $this->getOrderInfo($this->checkoutSession->getLastRealOrderId());
        $this->setOrderStatus($order->getId(), Order::STATE_PENDING_PAYMENT);

        $address = $order->getBillingAddress();

        $result = $this->dnaPayment->auth([
            'client_id' => $this->isTestMode ? $this->config->getClientIdTest($this->storeId) : $this->config->getClientId($this->storeId),
            'client_secret' => $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId),
            'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
            'invoiceId' => $order->getIncrementId(),
            'currency' => $order->getOrderCurrencyCode(),
            'amount' => $order->getBaseGrandTotal(),
            'paymentFormURL' => $this->storeManager->getStore()->getBaseUrl()
        ]);

        return $this->dnaPayment->generateUrl([
                'postLink' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/confirm'),
                'failurePostLink' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/close'),
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
    protected function getOrderInfo($incrementId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (empty($order->getId())) {
            throw new Error(__('Error: Can not find order by increment id'));
        }
        return $order;
    }

    public function setOrderStatus($orderId, $status)
    {
        $order = $this->orderRepository->get($orderId);
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
     * @param string $reference
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
        $reference = null,
        $signature = null,
        $errorCode = null,
        $success = null
    ) {
        $orderByIncrement = $this->getOrderInfo($invoiceId);
        $order = $this->orderRepository->get($orderByIncrement->getId());
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
            $this->setOrderStatus($orderByIncrement->getId(), $this->config->getOrderSuccessStatus());
            $orderPayment->setAdditionalInformation("paymentResponse", [
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
     * @param string $secure3D
     * @param string $reference
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @return void
     * @throws Exception
     */
    public function closeOrder(
        $invoiceId,
        $id = null,
        $amount = null,
        $currency = null,
        $accountId = null,
        $message = null,
        $secure3D = null,
        $reference = null,
        $signature = null,
        $errorCode = null,
        $success = null
    ) {
        $orderByIncrement = $this->getOrderInfo($invoiceId);
        $order = $this->orderRepository->get($orderByIncrement->getId());
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
            $this->setOrderStatus($orderByIncrement->getId(), $order::STATE_CLOSED);
            $orderPayment->setAdditionalInformation("paymentResponse", [
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
