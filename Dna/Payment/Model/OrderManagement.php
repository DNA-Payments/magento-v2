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
use DNAPayments\Util\RequestException;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use Magento\Setup\Exception;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Dna\Payment\Helper\DnaLogger;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Session\Generic as SessionManager;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;


/**
 * Guest payment information management model.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderManagement implements \Dna\Payment\Api\OrderManagementInterface
{
    private static $ccMapper = [
        'americanexpress' => 'AE',
        'amex' => 'AE',
        'amex_cpc' => 'AE',
        'diners' => 'DN',
        'dinersclub' => 'DN',
        'discover' => 'DI',
        'jcb' => 'JCB',
        'maestro' => 'MI',
        'uk_maestro' => 'MI',
        'mastercard' => 'MC',
        'mastercard_one' => 'MC',
        'mastercard_debit' => 'MC',
        'visa' => 'VI',
        'visa_atm_only' => 'VI',
        'visa_cpc_mi_only' => 'VI',
        'visa_cpc_vat' => 'VI',
        'visa_debit' => 'VI',
        'visa_electron' => 'VI',
        'visa_purchasing' => 'VI',
        'unionpay' => 'UN',
        'unionpay_amex' => 'UN',
        'unionpay_diners' => 'UN',
        'unionpay_jcb' => 'UN',
        'unionpay_maestro' => 'UN',
        'unionpay_mastercard' => 'UN',
        'unionpay_visa' => 'UN',
    ];


    protected $orderRepository;
    protected $orderFactory;
    protected $checkoutSession;
    protected $config;
    protected $session;
    protected $storeManager;
    protected $urlBuilder;
    protected $backendUrlManager;
    protected $isTestMode;
    protected $dnaPayment;
    protected $storeId;
    protected $paymentTokenRepository;
    protected $paymentTokenFactory;
    protected $encryptor;
    protected $paymentTokenManagement;
    protected $dnaLogger;
    protected $eventManager;
    protected $searchCriteriaBuilder;

    protected $sessionManager;
    protected $quoteIdMaskFactory;
    protected $cartRepository;
    protected $customerSession;
    protected $orderCollectionFactory;


    public function __construct(
        OrderRepositoryInterface        $orderRepository,
        OrderFactory                    $orderFactory,
        Session                         $checkoutSession,
        Config                          $config,
        SessionManagerInterface         $session,
        StoreManagerInterface           $storeManager,
        UrlInterface                    $urlBuilder,
        \Magento\Backend\Model\Url      $backendUrlManager,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        PaymentTokenFactoryInterface    $paymentTokenFactory,
        EncryptorInterface              $encryptor,
        PaymentTokenManagement          $paymentTokenManagement,
        DnaLogger                       $dnaLogger,
        EventManager                    $eventManager,
        SearchCriteriaBuilder           $searchCriteriaBuilder,
        SessionManager                  $sessionManager,
        QuoteIdMaskFactory              $quoteIdMaskFactory,
        CartRepositoryInterface         $cartRepository,
        CustomerSession                 $customerSession,
        OrderCollectionFactory          $orderCollectionFactory
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
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
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->encryptor = $encryptor;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->dnaLogger = $dnaLogger;
        $this->eventManager = $eventManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sessionManager = $sessionManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->customerSession = $customerSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    public function getAddress($address)
    {
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
                    'accountId' => $order->getCustomerId() ? $order->getCustomerId() : '',
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

    public function getUrl($url)
    {
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
     * @return array
     * @throws RequestException
     */
    public function startAndGetOrder()
    {
        $this->eventManager->dispatch('dna_payment_analytics_event');

        $order = $this->checkoutSession->getLastRealOrder();
        $result = $this->dnaPayment->auth($this->getAuthData($order));
        $cards = [];
        $customerId = $order->getCustomerId();
        if ($customerId) {
            $tokens = $this->paymentTokenManagement->getVisibleAvailableTokens($customerId);

            foreach ($tokens as $token) {
                $details = json_decode($token->getTokenDetails() ?: '{}', true);

                if (
                    $token->getPaymentMethodCode() == \Dna\Payment\Model\Config::PAYMENT_CODE &&
                    isset($details['cardholderName']) &&
                    isset($details['cardSchemeId']) &&
                    isset($details['cardSchemeName']) &&
                    isset($details['panStar']) &&
                    isset($details['expirationDate'])
                ) {
                    $encryptedToken = $token->getGatewayToken();
                    $cardTokenId = $this->encryptor->decrypt($encryptedToken);

                    $cards[] = [
                        'merchantTokenId' => $cardTokenId,
                        'cardSchemeId' => $details['cardSchemeId'],
                        'cardSchemeName' => $details['cardSchemeName'],
                        'panStar' => $details['panStar'],
                        'cardName' => $details['cardholderName'],
                        'expiryDate' => $details['expirationDate'],
                    ];
                }
            }
        }

        return [
            'paymentData' => $this->getPaymentData($order),
            'auth' => $result,
            'isTestMode' => $this->isTestMode,
            'integrationType' => $this->config->getIntegrationType($this->storeId),
            'savedCards' => $cards
        ];
    }

    /**
     *
     * @return array
     * @throws Error
     * @throws RequestException
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
     *
     * @return array
     * @throws Error
     * @throws RequestException
     * @throws NoSuchEntityException
     */
    public function getOrderPaymentData($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $result = $this->dnaPayment->auth($this->getAuthData($order));
        return [
            'paymentData' => $this->getPaymentData($order),
            'auth' => $result,
            'isTestMode' => $this->isTestMode,
            'integrationType' => $this->config->getIntegrationType($this->storeId),
        ];
    }

    /**
     * Get payment data for alternative payment methods, i.e. Google Pay / Apple Pay
     * @return array
     * @throws NoSuchEntityException
     * @throws RequestException
     */
    public function getQuotePaymentData($quoteId)
    {
        if ($this->customerSession->isLoggedIn()) {
            $quote = $this->cartRepository->get($quoteId);
        } else {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'masked_id');
            $quote = $this->cartRepository->get($quoteIdMask->getQuoteId());
        }

        if (!$quote) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(__('Quote not found.'));
        }

        $client_id = $this->isTestMode ? $this->config->getClientIdTest($this->storeId) : $this->config->getClientId($this->storeId);
        $order_id = $this->generateOrderId($client_id);

        $authData = $this->dnaPayment->auth(
            [
                'client_id' => $client_id,
                'client_secret' => $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId),
                'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
                'invoiceId' => $order_id,
                'currency' => $quote->getQuoteCurrencyCode(),
                'amount' => floatval($quote->getGrandTotal())
            ]
        );

        $response = [
            'paymentData' => [
                'invoiceId' => $order_id,
                'description' => 'Pay with your credit card via our payment gateway.',
                'amount' => floatval($quote->getGrandTotal()),
                'currency' => $quote->getQuoteCurrencyCode(),
                'paymentSettings' => [
                    'terminalId' => $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId),
                    'returnUrl' => $this->config->getBackLink($this->storeId) ? $this->urlBuilder->getUrl($this->config->getBackLink($this->storeId)) : $this->urlBuilder->getUrl('checkout/onepage/success'),
                    'failureReturnUrl' => $this->urlBuilder->getUrl('dna/result/failure'),
                    'callbackUrl' => $this->getUrl('rest/V1/dna-payment/confirm'),
                    'failureCallbackUrl' => $this->getUrl('rest/V1/dna-payment/failure'),
                ],
                'customerDetails' => [
                    'email' => $quote->getCustomerEmail(),
                    'accountDetails' => [
                        'accountId' => $quote->getCustomerId() ? $quote->getCustomerId() : '',
                    ],
                    'billingAddress' => [
                        'firstName' => $quote->getBillingAddress()->getFirstname(),
                        'lastName' => $quote->getBillingAddress()->getLastname(),
                        'addressLine1' => join(" ", $quote->getBillingAddress()->getStreet()),
                        'postalCode' => $quote->getBillingAddress()->getPostcode(),
                        'city' => $quote->getBillingAddress()->getCity(),
                        'region' => $quote->getBillingAddress()->getRegion(),
                        'phone' => $quote->getBillingAddress()->getTelephone(),
                        'country' => $quote->getBillingAddress()->getCountryId()
                    ],
                    'deliveryDetails' => [
                        'deliveryAddress' => [
                            'firstName' => $quote->getShippingAddress()->getFirstname(),
                            'lastName' => $quote->getShippingAddress()->getLastname(),
                            'addressLine1' => join(" ", $quote->getShippingAddress()->getStreet()),
                            'postalCode' => $quote->getShippingAddress()->getPostcode(),
                            'city' => $quote->getShippingAddress()->getCity(),
                            'region' => $quote->getShippingAddress()->getRegion(),
                            'phone' => $quote->getShippingAddress()->getTelephone(),
                            'country' => $quote->getShippingAddress()->getCountryId(),
                        ]
                    ]
                ],
                'amountBreakdown' => [
                    'itemTotal' => ['totalAmount' => round(floatval($quote->getSubtotal()), 2)],
                    'shipping' => ['totalAmount' => round(floatval($quote->getShippingAddress()->getShippingInclTax()), 2)],
                    // TODO: get tax
//                    'taxTotal' => ['totalAmount' => floatval($quote->getTaxAmount() ? $quote->getTaxAmount() : 0)],
                    // TODO: get discount
//                    'discount' => ['totalAmount' => abs($quote->getDiscountAmount())]
                ],
                'orderLines' => [],
                'merchantCustomData' => $this->convertDetailsToJSON([
                    'quoteId' => $quote->getId()
                ])
            ],
            'auth' => $authData,
            'isTestMode' => $this->isTestMode,
        ];

        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();

            $response['paymentData']['orderLines'][] = [
                'reference' => strval($item->getProductId()),
                'name' => $item->getName(),
                'quantity' => (int)$item->getQtyOrdered(),
                'unitPrice' => round((float)$item->getPrice(), 2),
                'imageUrl' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
                'productUrl' => $product->getProductUrl(),
                'totalAmount' => round((float)$item->getRowTotal(), 2)
            ];
        }

        $paymentAction = $this->config->getPaymentAction($this->storeId);
        if ($paymentAction !== ModelConfig::PAYMENT_PAYMENT_ACTION_DEFAULT) {
            $response['paymentData']['transactionType'] = ModelConfig::getTransactionType($paymentAction);
        }

        return $response;
    }

    /**
     * Generate an alphanumeric, Base62-encoded, SHA-256 hash-based order ID
     *
     * @param string $client_id
     * @return string
     */
    public function generateOrderId($client_id): string
    {
        $timestamp = microtime(true);
        $randomNumber = rand(1000, 9999);
        $dataToHash = $client_id . $timestamp . $randomNumber;

        $hash = hash('sha256', $dataToHash, true);

        $base64Hash = base64_encode($hash);
        $base64Hash = str_replace(['+', '/', '='], '', $base64Hash);

        return substr($base64Hash, 0, 16);
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
     * @return array
     **/
    public function cancelOrder($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        try {
            $order->cancel()->save();
        } catch (\Exception $e) {
            $this->dnaLogger->logException('Failed to cancel order', $e, [
                'order_id' => $orderId
            ]);

            throw new Error(__('Failed to cancel order ' . $orderId));
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
            $this->dnaLogger->logException('Failed to update order status', $e, [
                'order_id' => $orderId,
                'status' => $status,
            ]);
            throw new Error(__('Failed to update order ID ' . $orderId . ' with status ' . $status));
        }
    }

    private static function isDNAPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        $paymentMethodCode = $order->getPayment()->getMethodInstance()->getCode();
        return strpos($paymentMethodCode, 'dna_payment') === 0;
    }

    public static function isPendingPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        return $order->getState() == $order::STATE_PENDING_PAYMENT;
    }

    public static function isClosedPaymentOrder(\Magento\Sales\Model\Order $order)
    {
        return $order->getState() == $order::STATE_CLOSED
            || $order->getState() == $order::STATE_COMPLETE
            || $order->getState() == $order::STATE_PROCESSING;
    }

    private function savePayPalOrderDetail(\Magento\Sales\Model\Order $order, $input, $isAddOrderNode)
    {
        try {
            $orderPayment = $order->getPayment();
            $status = $input['paypalOrderStatus'];
            $captureStatus = $input['paypalCaptureStatus'];
            $reason = isset($input['paypalCaptureStatusReason']) ? $input['paypalCaptureStatusReason'] : null;

            $orderAdditionalStatus = $orderPayment->getAdditionalInformation('paypalOrderStatus');
            $orderAdditionalCaptureStatus = $orderPayment->getAdditionalInformation('paypalCaptureStatus');
            $orderAdditionalCaptureStatusReason = $orderPayment->getAdditionalInformation('paypalCaptureStatusReason');

            if ($isAddOrderNode) {
                $errorText = '';

                if ($orderAdditionalStatus !== $status) {
                    $errorText .= sprintf('DNA Payments paypal status was changed from "%s" to "%s". ', $orderAdditionalStatus, $status);
                }

                if ($orderAdditionalCaptureStatus !== $captureStatus) {
                    if ($errorText === '') {
                        $errorText .= sprintf('DNA Payments paypal capture status was changed from "%s" to "%s". ', $orderAdditionalCaptureStatus, $captureStatus);
                    } else {
                        $errorText .= sprintf('Capture status was changed from "%s" to "%s". ', $orderAdditionalCaptureStatus, $captureStatus);
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
            $orderPayment->setAdditionalInformation('paypalCaptureStatus', $captureStatus);
            $orderPayment->setAdditionalInformation('paypalCaptureStatusReason', $reason);
            $orderPayment->save();
        } catch (\Magento\Framework\Mail\Exception $exception) {
            $this->dnaLogger->logException('Failed to save PayPal order detail', $exception, [
                'order_id' => $order->getId()
            ]);
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
            $this->dnaLogger->logException('Failed to send email for order ID ' . $orderId, $exception);
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
     * @param string $cardTokenId
     * @param string $cardExpiryDate
     * @param string $cardSchemeId
     * @param string $cardSchemeName
     * @param string $cardPanStarred
     * @param string $storeCardOnFile
     * @param string $cardholderName
     * @param string $merchantCustomData
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
        $paypalOrderStatus = null,
        $cardTokenId = null,
        $cardExpiryDate = null,
        $cardSchemeId = null,
        $cardSchemeName = null,
        $cardPanStarred = null,
        $storeCardOnFile = null,
        $cardholderName = null,
        $merchantCustomData = null
    )
    {
        $secret = $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId);
        $isValidSignature = $this->dnaPayment->isValidSignature([
            'id' => $id,
            'amount' => $amount,
            'currency' => $currency,
            'invoiceId' => $invoiceId,
            'errorCode' => $errorCode,
            'success' => $success,
            'signature' => $signature
        ], $secret);

        $dna_order_number = $invoiceId;
        if (
            $paymentMethod == "googlepay" ||
            $paymentMethod == "applepay" ||
            $paymentMethod == "alipay_plus" ||
            $paymentMethod == "wechatpay" ||
            $paymentMethod == "clicktopay"
        ) {
            $merchantCustomDataJson = json_decode($merchantCustomData ?: '{}', true);

            if (!isset($merchantCustomDataJson['quoteId'])) {
                throw new \Exception('No quoteId found with merchantCustomData');
            }

            $quoteId = $merchantCustomDataJson['quoteId'];

            $orderCollection = $this->orderCollectionFactory->create()
                ->addFieldToFilter('quote_id', $quoteId);
            $order = $orderCollection->getFirstItem();

            if (!$order || !$order->getId()) {
                throw new \Exception('No order found with quote ID: ' . $quoteId);
            }

            $invoiceId = $order->getIncrementId();
        } else {
            $order = Helpers::getOrderInfo($invoiceId);
        }

        if (!$this->isDNAPaymentOrder($order)) {
            return;
        }

        if ($isValidSignature && $success) {
            try {
                $orderPayment = $order->getPayment();
                $isCompletedOrder = $this->isClosedPaymentOrder($order);

                if (!$isCompletedOrder) {
                    $this->setOrderStatus($invoiceId, Order::STATE_PENDING_PAYMENT);
                    $order = Helpers::getOrderInfo($invoiceId);

                    $orderPayment
                        ->setTransactionId($id)
                        ->addTransaction($settled ? Transaction::TYPE_CAPTURE : Transaction::TYPE_AUTH, null, true)
                        ->setIsTransactionClosed($settled)
                        ->save();

                    $orderPayment->setAdditionalInformation('id', $id);
                    $orderPayment->setAdditionalInformation('dnaOrderNumber', $dna_order_number);
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

                    $customerId = $order->getCustomerId();

                    if ($customerId && $paymentMethod == "card") {
                        $integration_type = $this->config->getIntegrationType($this->storeId);

                        if ($integration_type == '2') {
                            $merchantCustomDataJson = json_decode($merchantCustomData ?: '{}', true);

                            if (isset($merchantCustomDataJson['storeCardOnFile'])) {
                                $storeCardOnFile = $merchantCustomDataJson['storeCardOnFile'];
                            }
                        }
                        if ($storeCardOnFile) {
                            $this->saveToken($customerId, $cardholderName, $cardTokenId, $cardSchemeId, $cardSchemeName, $cardPanStarred, $cardExpiryDate);
                        }
                    }
                } else if (!empty($paypalCaptureStatus)) {
                    $this->savePayPalOrderDetail($order, [
                        'paypalCaptureStatus' => $paypalCaptureStatus,
                        'paypalCaptureStatusReason' => $paypalCaptureStatusReason,
                        'paypalOrderStatus' => $paypalOrderStatus
                    ], true);
                }
            } catch (\Magento\Checkout\Exception $e) {
                $this->dnaLogger->logException('Failed to confirm order for invoice ID ' . $invoiceId, $e);
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
    )
    {
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

    private function saveToken($customerId, $cardholderName, $cardTokenId, $cardSchemeId, $cardType, $maskedCC, $cardExpiryDate)
    {
        $lastFourDigits = substr($maskedCC, -4);
        $tokenDetails = $this->convertDetailsToJSON(
            [
                'type' => $this->getCardTypeCode($cardType),
                'maskedCC' => $lastFourDigits,
                'expirationDate' => $cardExpiryDate,
                'cardSchemeId' => $cardSchemeId,
                'cardSchemeName' => $cardType,
                'panStar' => $maskedCC,
                'cardholderName' => $cardholderName
            ]
        );
        $publicHash = $this->generatePublicHash($customerId, PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD, $tokenDetails);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId)
            ->addFilter('payment_method_code', \Dna\Payment\Model\Config::PAYMENT_CODE)
            ->addFilter('public_hash', $publicHash)
            ->create();

        $paymentTokens = $this->paymentTokenRepository->getList($searchCriteria)->getItems();
        $paymentToken = null;
        if (!empty($paymentTokens) && count($paymentTokens) === 1) {
            foreach ($paymentTokens as $token) {
                $paymentToken = $token;
            }
        } else {
            $paymentToken = $this->paymentTokenFactory->create(
                PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
            );
        }

        $paymentToken->setCustomerId($customerId);
        $paymentToken->setPaymentMethodCode(\Dna\Payment\Model\Config::PAYMENT_CODE);
        $paymentToken->setTokenDetails($tokenDetails);
        $paymentToken->setPublicHash($publicHash);
        $paymentToken->setExpiresAt($this->getExpirationDate($cardExpiryDate));
        $paymentToken->setGatewayToken($this->encryptor->encrypt($cardTokenId));
        $paymentToken->setIsActive(true);
        $paymentToken->setIsVisible(true);

        $this->paymentTokenRepository->save($paymentToken);
    }

    protected function getExpirationDate($expiryDate)
    {
        $dateParts = explode('/', $expiryDate);
        $year = strlen($dateParts[1]) == 2 ? '20' . $dateParts[1] : $dateParts[1];

        return sprintf('%s-%s-01 00:00:00', $year, $dateParts[0]);
    }

    protected function convertDetailsToJSON($details)
    {
        return json_encode($details, JSON_UNESCAPED_UNICODE);
    }

    private function generatePublicHash($customerId, $token_type, $tokenDetails)
    {
        $hashKey = $customerId;

        $hashKey .= \Dna\Payment\Model\Config::PAYMENT_CODE
            . $token_type
            . $tokenDetails;

        return $this->encryptor->getHash($hashKey);
    }

    public static function getCardTypeCode($cardType)
    {
        $cardTypeCode = str_replace([' ', '(', ')'], ['_', '', ''], strtolower($cardType));

        return isset(self::$ccMapper[$cardTypeCode]) ? self::$ccMapper[$cardTypeCode] : null;
    }
}
