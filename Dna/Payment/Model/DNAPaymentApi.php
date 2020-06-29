<?php
namespace Dna\Payment\Model;
use Dna\Payment\Model\HTTPRequester;
use Dna\Payment\Gateway\Config\Config;


class DNAPaymentApi {
    protected $logger;
    protected $config;
    protected $session;
    protected $isTestMode = true;
    private $fiels = array(
        "authUrl" => 'https://oauth.dnapayments.com/oauth2/token',
        'testAuthUrl' => 'https://test-oauth.dnapayments.com/oauth2/token',
    );

    private function getPath() {
        if ($this->isTestMode) return (object) array(
            "authUrl" => $this->fiels['testAuthUrl']
        );
        return (object) array(
            "authUrl" => $this->fiels['authUrl']
        );
    }

    public function __construct()
    {
        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface');
        $this->config = \Magento\Framework\App\ObjectManager::getInstance()->get('Dna\Payment\Gateway\Config\Config');
        $this->session = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\Session\SessionManagerInterface');
    }

    public function configure() {
        $storeId = $this->session->getStoreId();
        $this->isTestMode = $this->config->getTestMode($storeId);
    }

    public function auth($order) {
        $storeId = $this->session->getStoreId();

        $response = HTTPRequester::HTTPPost($this->getPath()->authUrl, array(
            'grant_type' => 'client_credentials',
            'scope' => 'payment integration_hosted',
            'client_id' => $this->isTestMode ? $this->config->getClientIdTest($storeId) : $this->config->getClientId($storeId),
            'client_secret' => $this->isTestMode ? $this->config->getClientSecretTest($storeId) : $this->config->getClientSecret($storeId),
            'terminal' => $this->isTestMode ? $this->config->getTerminalIdTest($storeId) : $this->config->getTerminalId($storeId),
            'invoiceId' => $order->invoiceId,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'paymentFormURL' => ''

        ));
        $this->logger->debug(100, $response);
        if ($response != null && $response['status'] >= 400 && $response['status'] < 400) {
            return $response['response'];
        }

    }
}