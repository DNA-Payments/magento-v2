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
use Magento\Sales\Model\Order;
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

    public function getShippingAddress(Order $order)
    {
        $address = $order->getShippingAddress();
        if ($address === null) {
            return null;
        }

        $streetLines = $address->getStreet();
        return [
            'firstName' => $address->getFirstname(),
            'lastName'  => $address->getLastname(),
            'streetAddress1'  => !empty($streetLines) && array_key_exists(0, $streetLines) ? $streetLines[0] : '',
            'streetAddress2'  => !empty($streetLines) && array_key_exists(1, $streetLines) ? $streetLines[1] : '',
            'streetAddress3'  => !empty($streetLines) && array_key_exists(2, $streetLines) ? $streetLines[2] : '',
            'city'       => $address->getCity(),
            'region'      => $address->getRegion(),
            'postalCode'   => $address->getPostcode(),
            'country'    => $address->getCountryId()
        ];
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
        $total = round((float)$order->getGrandTotal(), 2);
        $productTotal = round((float)$this->getProductTotalAmount($order), 2);
        $shippingTotal = round((float)$order->getShippingAmount(), 2);
        $taxTotal = round((float)$order->getTaxAmount(), 2);
        $handlingTotal = round((float)($total - $productTotal - $shippingTotal - $taxTotal), 2);

        return [
            'itemTotal' => ['totalAmount' => $productTotal],
            'shipping' => ['totalAmount' => $shippingTotal],
            'handling' => ['totalAmount' => $handlingTotal],
            'taxTotal' => ['totalAmount' => $taxTotal]
        ];
    }

    public function getOrderLines(Order $order)
    {
        $orderLines = [];

        foreach ($order->getAllVisibleItems() as $item_id => $item) {
            $orderLines[] = [
                'reference' => strval($item->getProductId()),
                'name' => $item->getName(),
                'quantity' => (int)$item->getQtyOrdered(),
                'unitPrice' => round((float)$item->getPrice(), 2),
                'totalAmount' => round((float)$item->getRowTotal(), 2)
            ];
        }

        return $orderLines;
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
            'amount' => $order->getGrandTotal()
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
                'amount' => $order->getGrandTotal(),
                'accountId' => $this->checkoutSession->getCustomer() ? $this->checkoutSession->getCustomer()->getId() : '',
                'accountCountry' => $address->getCountryId(),
                'accountCity' => $address->getCity(),
                'accountStreet1' => join(" ", $address->getStreet()),
                'accountEmail' => $address->getEmail(),
                'accountFirstName' => $address->getFirstname(),
                'accountLastName' => $address->getLastname(),
                'accountPostalCode' => $address->getPostcode(),
                'language' => 'eng',
                'shippingAddress' => $this->getShippingAddress($order),
                'amountBreakdown' => $this->getAmountBreakDown($order),
                'orderLines' => $this->getOrderLines($order)
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

    private static function isDNAPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        return 'dna_payment' === $order->getPayment()->getMethodInstance()->getCode();
    }

    public static function isPendingPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        return $order->getState() == $order::STATE_PENDING_PAYMENT;
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
        $paymentMethod = null,
        $paypalCaptureStatus = null,
        $paypalCaptureStatusReason = null,
        $paypalOrderStatus = null
    ) {
        $order = $this->getOrderInfo($invoiceId);

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
                $isCompletedOrder = !$this->isPendingPaymentOrder($order);
                if ($isCompletedOrder && !empty($paypalCaptureStatus)) {
                    $this->savePayPalOrderDetail($order, [
                        'paypalCaptureStatus' => $paypalCaptureStatus,
                        'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                        'paypalOrderStatus' => $paypalOrderStatus
                    ], true);
                } else {
                    $orderPayment
                        ->setTransactionId($id)
                        ->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true)
                        ->save();
                    $orderPayment->setAdditionalInformation('id', $id);
                    $orderPayment->setAdditionalInformation('rrn', $rrn);
                    $orderPayment->setAdditionalInformation('message', $message);
                    $orderPayment->setAdditionalInformation('paymentMethod', $paymentMethod);
                    $orderPayment->save();


                    $order
                        ->addStatusHistoryComment("Your payment with DNA Payment is complete. Transaction #$id")
                        ->setIsCustomerNotified(true)
                        ->save();

                    if (!empty($paypalCaptureStatus)) {
                        $this->savePayPalOrderDetail($order, [
                            'paypalCaptureStatus' => $paypalCaptureStatus,
                            'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                            'paypalOrderStatus' => $paypalOrderStatus
                        ], false);
                    }

                    $this->setOrderStatus($invoiceId, $this->config->getOrderSuccessStatus());
                    $this->sendEmail($invoiceId);
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
        $paypalCaptureStatus = null,
        $paypalCaptureStatusReason = null,
        $paypalOrderStatus = null
    ) {
        $order = $this->getOrderInfo($invoiceId);

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
            'signature' => $signature
        ], $secret)) {
            $isCompletedOrder = !$this->isPendingPaymentOrder($order);

            if ($isCompletedOrder && !empty($paypalCaptureStatus)) {
                $this->savePayPalOrderDetail($order, [
                    'paypalCaptureStatus' => $paypalCaptureStatus,
                    'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                    'paypalOrderStatus' => $paypalOrderStatus
                ], true);
            } else {
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

                $this->setOrderStatus($invoiceId, $order::STATE_CLOSED);
            }
        }
        return $invoiceId;
    }
}
