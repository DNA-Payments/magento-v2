<?php
namespace Dna\Payment\Model;

use Magento\Setup\Exception;

class DNAPaymentApi
{
    protected $logger;
    protected $config;
    protected $session;
    protected $storeManager;
    protected $urlBuilder;
    protected $isTestMode = false;

    public $authToken;
    private $fiels = [
        'authUrl' => 'https://oauth.dnapayments.com/oauth2/token',
        'testAuthUrl' => 'https://test-oauth.dnapayments.com/oauth2/token',
        'testPaymentPageUrl' => 'https://test-pay.dnapayments.com/checkout',
        'paymentPageUrl' => 'https://pay.dnapayments.com/checkout'
    ];

    private function getPath()
    {
        if ($this->isTestMode) {
            return (object) [
            'authUrl' => $this->fiels['testAuthUrl'],
            'paymentPageUrl' => $this->fiels['testPaymentPageUrl'],
        ];
        }
        return (object) [
            'authUrl' => $this->fiels['authUrl'],
            'paymentPageUrl' => $this->fiels['paymentPageUrl']
        ];
    }

    public function __construct()
    {
        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface');
        $this->config = \Magento\Framework\App\ObjectManager::getInstance()->get('Dna\Payment\Gateway\Config\Config');
        $this->session = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\Session\SessionManagerInterface');
        $this->storeManager = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Store\Model\StoreManagerInterface');
        $this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Framework\UrlInterface');
        $storeId = $this->session->getStoreId();
        $this->isTestMode = $this->config->getTestMode($storeId);
    }

    public function configure()
    {
    }

    public function auth($order)
    {
        $storeId = $this->session->getStoreId();

        $authData = [
            'grant_type' => 'client_credentials',
            'scope' => 'payment integration_hosted',
            'client_id' => $this->isTestMode ? $this->config->getClientIdTest($storeId) : $this->config->getClientId($storeId),
            'client_secret' => $this->isTestMode ? $this->config->getClientSecretTest($storeId) : $this->config->getClientSecret($storeId),
            'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($storeId) : $this->config->getTerminalId($storeId),
            'invoiceId' => strval($order->invoiceId),
            'amount' => floatval($order->amount),
            'currency' => strval($order->currency),
            'paymentFormURL' => $this->storeManager->getStore()->getBaseUrl()

        ];

        $response = HTTPRequester::HTTPPost($this->getPath()->authUrl, $authData);
        if ($response != null && $response['status'] >= 200 && $response['status'] < 400) {
            $this->authToken = $response['response'];
            return $response['response'];
        }

        throw new Exception('Error: No auth service');
    }

    public function generateUrl($order)
    {
        $storeId = $this->session->getStoreId();

        return $this->getPath()->paymentPageUrl . '/?params=' . $this->encodeToUrl((object) [
                'auth' => $this->authToken,
                'invoiceId' => strval($order->invoiceId),
                'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($storeId) : $this->config->getTerminalId($storeId),
                'amount' => floatval($order->amount),
                'currency' => strval($order->currency),
                'postLink' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/confirm'),
                'failurePostLink' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/close'),
                'backLink' => $this->config->getBackLink($storeId) ? $this->urlBuilder->getUrl($this->config->getBackLink($storeId)) : $this->urlBuilder->getUrl('checkout/onepage/success'),
                'failureBackLink' => $this->config->getFailureBackLink($storeId) ? $this->urlBuilder->getUrl($this->config->getFailureBackLink($storeId)) : $this->urlBuilder->getUrl('dna/result/failure'),
                'language' => 'eng',
                'description' => $this->config->getGatewayOrderDescription($storeId),
                'accountId' => $order->accountId,
                'accountCountry' => $order->accountCountry,
                'accountCity' => $order->accountCity,
                'accountStreet1' => $order->accountStreet1,
                'accountEmail' => $order->accountEmail,
                'accountFirstName' => $order->accountFirstName,
                'accountLastName' => $order->accountLastName,
                'accountPostalCode' => $order->accountPostalCode
        ]) . '&data=' . $this->encodeToUrl((object) [
               'isTest' => $this->isTestMode
        ]);
    }

    public function encodeToUrl($data)
    {
        return base64_encode(\LZCompressor\LZString::compressToEncodedURIComponent(json_encode($data)));
    }
}
