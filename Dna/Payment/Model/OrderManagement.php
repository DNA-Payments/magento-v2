<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Dna\Payment\Model;

use Dna\Payment\Gateway\Config\Config;
use Dna\Payment\Model\Config as ModelConfig;
use DNAPayments\DNAPayments;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
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
    protected $backendUrlManager;
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
        UrlInterface $urlBuilder,
        \Magento\Backend\Model\Url $backendUrlManager
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->config = $config;
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->backendUrlManager = $backendUrlManager;
        $this->storeId = $this->session->getStoreId();
        $this->isTestMode = $this->config->getTestMode($this->storeId);
        $this->dnaPayment = new DNAPayments(
            [
                'isTestMode' => $this->isTestMode,
                'scopes' => [
                    'allowHosted' => true,
                    'allowEmbedded' => $this->config->getIntegrationType($this->storeId) == '1'
                ]
            ]
        );
    }

    public function getAddress($address) {
        if ($address === null) {
            return null;
        }
        $streetLines = $address->getStreet();
        return array(
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname(),
            'addressLine1' => join(" ", $address->getStreet()),
            'postalCode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'region' => $address->getRegion(),
            'phone' => $address->getTelephone(),
            'country' => $address->getCountryId()
        );
    }

    public function getProductTotalAmount(Order $order)
    {
        $productTotal = 0;
        foreach ($order->getAllVisibleItems() as $item_id => $item) {
            $productTotal += round((float)$item->getRowTotal(), 2);
        }

        return $productTotal;
    }

    public function getAmountBreakDown(Order $order)
    {
        $productTotal = round($this->getProductTotalAmount($order), 2);
        $shippingTotal = round((float)$order->getShippingAmount(), 2);
        $taxTotal = round((float)$order->getTaxAmount(), 2);

        return [
            'itemTotal' => ['totalAmount' => $productTotal],
            'shipping' => ['totalAmount' => $shippingTotal],
            'taxTotal' => ['totalAmount' => $taxTotal],
            'discount' => ['totalAmount' => round(abs((float)$order->getDiscountAmount()), 2)],
            'shippingDiscount' => ['totalAmount' => round(abs((float)$order->getShippingDiscountAmount()), 2)]
        ];
    }

    public function getOrderLines(Order $order)
    {
        $orderLines = [];

        foreach ($order->getAllVisibleItems() as $item_id => $item) {
            $product = $item->getProduct();
            $orderLines[] = [
                'reference' => strval($item->getProductId()),
                'name' => $item->getName(),
                'quantity' => (int)$item->getQtyOrdered(),
                'unitPrice' => round((float)$item->getPrice(), 2),
                'imageUrl' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
                'productUrl' => $product->getProductUrl(),
                'totalAmount' => round((float)$item->getRowTotal(), 2)
            ];
        }

        return $orderLines;
    }

    public function getPaymentData($order)
    {
        $billingAddress = $order->getBillingAddress();
        $paymentAction = $this->config->getPaymentAction($this->storeId);

        $paymentData = [
            'invoiceId' => $order->getIncrementId(),
            'description' => $this->config->getGatewayOrderDescription($this->storeId),
            'amount' => floatval($order->getGrandTotal()),
            'currency' => $order->getOrderCurrencyCode(),
            'paymentSettings' => [
                'terminalId' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
                'returnUrl' => $this->config->getBackLink($this->storeId) ? $this->urlBuilder->getUrl($this->config->getBackLink($this->storeId)) : $this->urlBuilder->getUrl('checkout/onepage/success'),
                'failureReturnUrl' => $this->urlBuilder->getUrl('dna/result/failure'),
                'callbackUrl' => $this->getUrl('rest/V1/dna-payment/confirm'),
                'failureCallbackUrl' => $this->getUrl('rest/V1/dna-payment/failure'),
            ],
            'customerDetails' => [
                'email' => $billingAddress->getEmail(),
                'accountDetails' => [
                    'accountId' => $this->checkoutSession->getCustomer() ? $this->checkoutSession->getCustomer()->getId() : '',
                ],
                'billingAddress' => $this->getAddress($billingAddress),
                'deliveryDetails' => [
                    'deliveryAddress' => $this->getAddress($order->getShippingAddress()),
                ]
            ],
            'amountBreakdown' => $this->getAmountBreakDown($order),
            'orderLines' => $this->getOrderLines($order),
        ];

        if ($paymentAction !== ModelConfig::PAYMENT_PAYMENT_ACTION_DEFAULT) {
            $paymentData['transactionType'] = ModelConfig::getTransactionType($paymentAction);
        }

        return $paymentData;
    }

    public function getUrl($url) {
        return $this->storeManager->getStore()->getBaseUrl() . $url;
    }

    public function getAuthData($order)
    {
        return [
            'client_id' => $this->isTestMode ? $this->config->getClientIdTest($this->storeId) : $this->config->getClientId($this->storeId),
            'client_secret' => $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId),
            'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
            'invoiceId' => $order->getIncrementId(),
            'currency' => $order->getOrderCurrencyCode(),
            'amount' => floatval($order->getGrandTotal())
        ];
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
        $result = $this->dnaPayment->auth($this->getAuthData($order));
        return [
            'paymentData' => $this->getPaymentData($order),
            'auth' => $result,
            'isTestMode' => $this->isTestMode,
            'integrationType' => $this->config->getIntegrationType($this->storeId)
        ];
    }

    /**
     *
     * @return void
     * @throws Error
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getDnaPaymentData($orderId)
    {
        $order = Helpers::getOrderInfo($orderId);
        $result = $this->dnaPayment->auth($this->getAuthData($order));
        return [
            'paymentData' => $this->getPaymentData($order),
            'auth' => $result,
            'isTestMode' => $this->isTestMode,
            'integrationType' => $this->config->getIntegrationType($this->storeId),
            'adminOrderViewUrl' => $this->backendUrlManager->getUrl('sales/order/view', ['order_id' => $order->getId()])
        ];
    }

    /**
    * Get dumb auth data for validating
    * @return object
    **/
    public function getDnaDumbAuthData()
    {
        $auth = $this->dnaPayment->auth([
            'client_id' => $this->isTestMode ? $this->config->getClientIdTest($this->storeId) : $this->config->getClientId($this->storeId),
            'client_secret' => $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId),
            'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
            'invoiceId' => null,
            'currency' => 'GBP',
            'amount' => floatval(1)
        ]);
        return [
            'accessToken' => $auth['access_token'],
            'isTest' => $this->isTestMode,
        ];
    }

    /**
    * Cancel order
    * @param string $orderId
    * @return void
    **/
    public function cancelOrder($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        try {
            $order->cancel()->save();
        } catch (\Exception $e) {
            throw new Error(__('Error can not cancel order ' + $orderId));
        }

        return [
            'id' => $order->getId()
        ];
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

    private static function isDNAPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        return 'dna_payment' === $order->getPayment()->getMethodInstance()->getCode();
    }

    public static function isPendingPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        return $order->getState() == $order::STATE_PENDING_PAYMENT;
    }

    public static function isClosedPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        return $order->getState() == $order::STATE_CLOSED || $order->getState() == $order::STATE_COMPLETE;
    }

    private function savePayPalOrderDetail(\Magento\Sales\Model\Order $order, $input, $isAddOrderNode)
    {
        try {
            $orderPayment = $order->getPayment();
            $status = $input['paypalOrderStatus'];
            $сaptureStatus = $input['paypalCaptureStatus'];
            $reason = isset($input['paypalCaptureStatusReason']) ? $input['paypalCaptureStatusReason'] : null;

            $orderAdditionalStatus = $orderPayment->getAdditionalInformation('paypalOrderStatus');
            $orderAdditionalCaptureStatus = $orderPayment->getAdditionalInformation('paypalCaptureStatus');
            $orderAdditionalCaptureStatusReason = $orderPayment->getAdditionalInformation('paypalCaptureStatusReason');

            if ($isAddOrderNode) {
                $errorText = '';

                if ($orderAdditionalStatus !== $status) {
                    $errorText .= sprintf('DNA Payments paypal status was changed from "%s" to "%s". ', $orderAdditionalStatus, $status);
                }

                if ($orderAdditionalCaptureStatus !== $сaptureStatus) {
                    if ($errorText === '') {
                        $errorText .= sprintf('DNA Payments paypal capture status was changed from "%s" to "%s". ', $orderAdditionalCaptureStatus, $сaptureStatus);
                    } else {
                        $errorText .= sprintf('Capture status was changed from "%s" to "%s". ', $orderAdditionalCaptureStatus, $сaptureStatus);
                    }
                }

                if ($orderAdditionalCaptureStatusReason !== $reason) {
                    if ($errorText === '') {
                        $errorText .= ($reason ? 'DNA Payments paypal capture status reason was changed: ' . $reason . '.' : '');
                    } else {
                        $errorText .= ($reason ? 'Reason:  ' . $reason . '.' : '');
                    }
                }

                if (strlen($errorText) > 0) {
                    $order
                        ->addStatusHistoryComment($errorText)
                        ->setIsCustomerNotified(false)
                        ->save();
                }
            }

            $orderPayment->setAdditionalInformation('paypalOrderStatus', $status);
            $orderPayment->setAdditionalInformation('paypalCaptureStatus', $сaptureStatus);
            $orderPayment->setAdditionalInformation('paypalCaptureStatusReason', $reason);
            $orderPayment->save();
        } catch (\Magento\Framework\Mail\Exception $exception) {
            $this->logger->info($exception);
            return false;
        }
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
            $order = Helpers::getOrderInfo($orderId);
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
     * @param boolean $settled
     * @param string $paymentMethod
     * @param string $paypalCaptureStatus
     * @param string $paypalCaptureStatusReason
     * @param string $paypalOrderStatus
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
        $success = null,
        $settled = null,
        $paymentMethod = null,
        $paypalCaptureStatus = null,
        $paypalCaptureStatusReason = null,
        $paypalOrderStatus = null
    ) {
        $order = Helpers::getOrderInfo($invoiceId);

        if (!$this->isDNAPaymentOrder($order)) {
            return;
        }
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
                $orderPayment = $order->getPayment();
                $isCompletedOrder = $this->isClosedPaymentOrder($order);
                
                if (!$isCompletedOrder){
                    $this->setOrderStatus($invoiceId, Order::STATE_PENDING_PAYMENT);
                    $order = Helpers::getOrderInfo($invoiceId);

                    $orderPayment
                        ->setTransactionId($id)
                        ->addTransaction($settled ? Transaction::TYPE_CAPTURE : Transaction::TYPE_AUTH, null, true)
                        ->setIsTransactionClosed($settled)
                        ->save();

                    $orderPayment->setAdditionalInformation('id', $id);
                    $orderPayment->setAdditionalInformation('rrn', $rrn);
                    $orderPayment->setAdditionalInformation('message', $message);
                    $orderPayment->setAdditionalInformation('paymentMethod', $paymentMethod);
                    $orderPayment->setAdditionalInformation(ModelConfig::ORDER_IS_FINISHED_PAYMENT_KEY, $settled ? 'yes' : 'no');
                    if ($settled) {
                        $orderPayment->setBaseAmountAuthorized($order->getBaseTotalDue());
                        $orderPayment->setAmountAuthorized($order->getTotalDue());
                    }
                    $orderPayment->save();
                    if ($settled) {
                        \Dna\Payment\Model\Helpers::invoiceOrder($order, $id);
                        $order
                            ->addStatusHistoryComment("Your payment with DNA Payment is complete. Transaction #$id")
                            ->setIsCustomerNotified(true)
                            ->save();
                    } else {
                        $order
                            ->addStatusHistoryComment("Your payment with DNA Payment is authorized. Transaction #$id")
                            ->setIsCustomerNotified(true)
                            ->save();
                    }

                    if (!empty($paypalCaptureStatus)) {
                        $this->savePayPalOrderDetail($order, [
                            'paypalCaptureStatus' => $paypalCaptureStatus,
                            'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                            'paypalOrderStatus' => $paypalOrderStatus
                        ], false);
                    }

                    if ($settled) {
                        $this->setOrderStatus($invoiceId, $this->config->getOrderSuccessStatus());
                    } else {
                        $this->setOrderStatus($invoiceId, Order::STATE_PAYMENT_REVIEW);
                    }
                    $this->sendEmail($invoiceId);
                } else if (!empty($paypalCaptureStatus)) {
                    $this->savePayPalOrderDetail($order, [
                        'paypalCaptureStatus' => $paypalCaptureStatus,
                        'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                        'paypalOrderStatus' => $paypalOrderStatus
                    ], true);
                }
            } catch (\Magento\Checkout\Exception $e) {
                $this->logger->error($e);
            }
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
     * @param string $rrn
     * @param string $signature
     * @param string $errorCode
     * @param boolean $success
     * @param boolean $settled
     * @param string $paymentMethod
     * @param string $paypalCaptureStatus
     * @param string $paypalCaptureStatusReason
     * @param string $paypalOrderStatus
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
        $success = null,
        $settled = null,
        $paymentMethod = null,
        $paypalCaptureStatus = null,
        $paypalCaptureStatusReason = null,
        $paypalOrderStatus = null
    ) {
        $order = Helpers::getOrderInfo($invoiceId);

        if (!$this->isDNAPaymentOrder($order)) {
            return;
        }

        $orderPayment = $order->getPayment();
        $secret = $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId);

        if ($this->dnaPayment->isValidSignature([
            'id' => $id,
            'amount' => $amount,
            'currency' => $currency,
            'invoiceId' => $invoiceId,
            'errorCode' => $errorCode,
            'success' => $success,
            'settled' => $settled,
            'signature' => $signature
        ], $secret)) {
            $isCompletedOrder = $this->isClosedPaymentOrder($order);

            if (!$isCompletedOrder) {
                $order->addStatusHistoryComment("Your payment with DNA Payment is failed. Transaction #$id");
                
                $orderPayment
                    ->setTransactionId($id)
                    ->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true)
                    ->save();

                $orderPayment->setAdditionalInformation('id', $id);
                $orderPayment->setAdditionalInformation('rrn', $rrn);
                $orderPayment->setAdditionalInformation('message', $message);
                $orderPayment->save();

                if (!empty($paypalCaptureStatus)) {
                    $this->savePayPalOrderDetail($order, [
                        'paypalCaptureStatus' => $paypalCaptureStatus,
                        'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                        'paypalOrderStatus' => $paypalOrderStatus
                    ], false);
                }

                $this->setOrderStatus($invoiceId, $order::STATE_CANCELED);
            } else if (!empty($paypalCaptureStatus)) {
                $this->savePayPalOrderDetail($order, [
                    'paypalCaptureStatus' => $paypalCaptureStatus,
                    'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                    'paypalOrderStatus' => $paypalOrderStatus
                ], true);
            }
        }

        return $invoiceId;
    }
}
