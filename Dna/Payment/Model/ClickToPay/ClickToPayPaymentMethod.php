<?php

namespace Dna\Payment\Model\ClickToPay;

use Dna\Payment\Gateway\Config\Config;
use Dna\Payment\Model\Helpers;
use Dna\Payment\Model\ClickToPay\CartFactory;
use Dna\Payment\Model\ClickToPay\Magento;
use Dna\Payment\Model\ClickToPay\Transaction;
use DNAPayments\DNAPayments;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class ClickToPayPaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected $_code = 'dna_payment_clicktopay';

    /**
     * @var string
     */
    protected $_formBlockType = \Dna\Payment\Block\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \Dna\Payment\Block\Info::class;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Availability option
     *
     * @var bool
     */

    protected $_canCancelInvoice = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * Availability option
     *
     * @var bool
     */

    protected $_canCaptureOnce = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    protected $_canReviewPayment = true;

    private $_newLogger;
    /**
     * @var Transaction\BuilderInterface|\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    private $transactionBuilder;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $_urlBuilder;
    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    private $transactionRepository;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;
    /**
     * @var \Magento\Framework\Exception\LocalizedExceptionFactory
     */
    private $_exception;

    /**
     * @var int
     */
    private $storeId;

    /**
     * @var bool
     */
    private $isTestMode;

    /**
     * @var DNAPayments
     */

    private $dnaPayment;
    /**
     * @var Config
     */
    private $config;
    private $clientId;
    private $clientSecret;
    private $terminalId;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $session;
    private $appState;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param CartFactory $cartFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Exception\LocalizedExceptionFactory $exception
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param Transaction\BuilderInterface $transactionBuilder
     * @param \Psr\Log\LoggerInterface
     * @param Magento\Framework\Session\SessionManagerInterface
     * @param Config
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        State $appState,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Exception\LocalizedExceptionFactory $exception,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Psr\Log\LoggerInterface $newLogger,
        \Magento\Framework\Session\SessionManagerInterface $session,
        Config $config,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->appState = $appState;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_storeManager = $storeManager;
        $this->_urlBuilder = $urlBuilder;
        $this->_checkoutSession = $checkoutSession;
        $this->_exception = $exception;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->_newLogger = $newLogger;
        $this->session = $session;
        $this->config = $config;
        $this->storeId = $this->session->getStoreId();
        $this->isTestMode = $this->config->getTestMode($this->storeId);
        $this->dnaPayment = new DNAPayments(
            [
                'isTestMode' => $this->isTestMode,
                'scopes' => [
                    'allowHosted' => true,
                    'allowSeamless' => true,
                    'allowEmbedded' => $this->config->getIntegrationType($this->storeId) == '1'
                ]
            ]
        );
        $this->clientId = $this->isTestMode ? $this->config->getClientIdTest($this->storeId) : $this->config->getClientId($this->storeId);
        $this->clientSecret = $this->isTestMode ? $this->config->getClientSecretTest($this->storeId) : $this->config->getClientSecret($this->storeId);
        $this->terminalId = $this->isTestMode ? $this->config->getTerminalIdTest($this->storeId) : $this->config->getTerminalId($this->storeId);
    }

    public function initialize($paymentAction, $stateObject)
    {
        try {
            $payment = $this->getInfoInstance();
            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();
            $order->setCanSendNewEmailFlag(false);
            $stateObject->setIsCustomerNotified(false);
            $stateObject->setIsNotified(false);
            $stateObject->setState(Order::STATE_PENDING_PAYMENT);
            $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
        } catch (LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Can not create a new order')
            );
        }
        return $this;
    }

    /**
     * Cancel payment
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @return $this
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->void($payment);
    }

    /**
     * Attempt to accept a pending payment
     *
     * @param \Magento\Payment\Model\Info|Payment $payment
     * @return bool
     */
    public function acceptPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        $order = $payment->getOrder();
        parent::acceptPayment($payment);
        $this->capture($payment, $order->getGrandTotal());
        Helpers::invoiceOrder($order, $payment->getLastTransId());
        return $this;
    }

    /**
     * Attempt to deny a pending payment
     *
     * @param \Magento\Payment\Model\InfoInterface|Payment $payment
     * @return bool
     */
    public function denyPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        parent::denyPayment($payment);
        return $this->void($payment);
    }

    /**
     * Void transaction
     *
     * @param \Magento\Framework\DataObject $payment
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $transactionId = $payment->getLastTransId();
        if (empty($transactionId)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You need an authorization transaction to void.')
            );
        }
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $this->dnaPayment->cancel([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'terminal' => $this->terminalId,
            'invoiceId' => strval($order->getIncrementId()),
            'amount' => round((float)$order->getGrandTotal(), 2),
            'currency' => $order->getBaseCurrencyCode(),
            'transaction_id' => $transactionId
        ]);

        return $this;
    }

    public function canRefund()
    {
        $paymentInfo = $this->getInfoInstance();
        return $this->_canRefund && Helpers::isValidStatusPayPalStatus($paymentInfo->getAdditionalInformation('paypalCaptureStatus'));
    }

    public function canReviewPayment()
    {
        $paymentInfo = $this->getInfoInstance();
        return $this->_canReviewPayment && Helpers::isValidStatusPayPalStatus($paymentInfo->getAdditionalInformation('paypalCaptureStatus'));
    }

    /**
     * Refund capture
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $transactionId = $payment->getParentTransactionId();

        if (empty($transactionId)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We can\'t issue a refund transaction because there is no capture transaction.')
            );
        }

        $order = $payment->getOrder();
        $this->dnaPayment->refund([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'terminal' => $this->terminalId,
            'invoiceId' => strval($order->getIncrementId()),
            'amount' => $amount,
            'currency' => $order->getBaseCurrencyCode(),
            'transaction_id' => $transactionId
        ]);
        return $this;
    }

    /**
     * Capture payment
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param float $amount
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @throws \Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $transactionId = $payment->getLastTransId();

        if (empty($transactionId)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We can\'t issue a capture transaction because there is no authorize transaction.')
            );
        }

        $order = $payment->getOrder();

        if ($payment->getAdditionalInformation(\Dna\Payment\Model\Config::ORDER_IS_FINISHED_PAYMENT_KEY) === 'no') {
            $this->dnaPayment->charge([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'terminal' => $this->terminalId,
                'invoiceId' => strval($order->getIncrementId()),
                'amount' => $amount,
                'currency' => $order->getBaseCurrencyCode(),
                'transaction_id' => $transactionId
            ]);

            $payment->setAdditionalInformation(\Dna\Payment\Model\Config::ORDER_IS_FINISHED_PAYMENT_KEY, 'yes');
            $order->setState($this->config->getOrderSuccessStatus());
            $order->setStatus($this->config->getOrderSuccessStatus());
            $order->save();
        }
        return $this;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        // Check if current area is adminhtml
        if ($this->appState->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML) {
            return false;
        }
        return parent::isAvailable($quote);
    }
}
