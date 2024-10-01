<?php

namespace Dna\Payment\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'active';
    const KEY_TERMINAL_ID = 'terminal_id';
    const KEY_CLIENT_ID = 'client_id';
    const KEY_CLIENT_SECRET = 'client_secret';
    const KEY_GATEWAY_ORDER_DESCRIPTION = 'gateway_order_description';
    const KEY_PAYMENT_ACTION = 'payment_action';
    const KEY_TEST_MODE = 'test_mode';

    const KEY_TERMINAL_ID_TEST = 'terminal_id_test';
    const KEY_CLIENT_ID_TEST = 'client_id_test';
    const KEY_CLIENT_SECRET_TEST = 'client_secret_test';

    const KEY_BACK_LINK = 'back_link';

    const KEY_ORDER_SUCCESS_STATUS = 'order_status_success';
    const KEY_INTEGRATION_TYPE = 'integration_type';

    protected $scopeConfig;

    /**
     * DNA config constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        string $methodCode = null,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);

        $this->scopeConfig = $scopeConfig;
    }

    public function isActive($storeId = null)
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }
    public function getTerminalId($storeId = null)
    {
        return $this->getValue(Config::KEY_TERMINAL_ID, $storeId);
    }

    public function getClientId($storeId = null)
    {
        return $this->getValue(Config::KEY_CLIENT_ID, $storeId);
    }

    public function getClientSecret($storeId = null)
    {
        return $this->getValue(Config::KEY_CLIENT_SECRET, $storeId);
    }

    public function getBackLink($storeId = null)
    {
        return $this->getValue(Config::KEY_BACK_LINK, $storeId);
    }

    public function getGatewayOrderDescription($storeId = null)
    {
        return $this->getValue(Config::KEY_GATEWAY_ORDER_DESCRIPTION, $storeId);
    }

    public function getPaymentAction($storeId = null)
    {
        return $this->getValue(Config::KEY_PAYMENT_ACTION, $storeId);
    }

    public function getIntegrationType($storeId = null)
    {
        return $this->getValue(Config::KEY_INTEGRATION_TYPE, $storeId);
    }

    public function getTestMode($storeId = null)
    {
        return (bool) $this->getValue(Config::KEY_TEST_MODE, $storeId);
    }

    public function getTerminalIdTest($storeId = null)
    {
        return $this->getValue(Config::KEY_TERMINAL_ID_TEST, $storeId);
    }

    public function getClientIdTest($storeId = null)
    {
        return $this->getValue(Config::KEY_CLIENT_ID_TEST, $storeId);
    }

    public function getClientSecretTest($storeId = null)
    {
        return $this->getValue(Config::KEY_CLIENT_SECRET_TEST, $storeId);
    }

    public function getOrderSuccessStatus($storeId = null)
    {
        return $this->getValue(Config::KEY_ORDER_SUCCESS_STATUS, $storeId);
    }

    public function isVaultEnabled()
    {
        $isVaultEnabled = $this->scopeConfig->getValue(
            'payment/dna_payment_cc_vault/active',
            ScopeInterface::SCOPE_STORE
        );

        return (bool)$isVaultEnabled;
    }
}
