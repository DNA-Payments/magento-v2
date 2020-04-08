<?php

namespace Dna\Payment\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;


class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'active';
    const KEY_TERMINAL_ID = 'terminal_id';
    const KEY_CLIENT_ID = 'client_id';
    const KEY_CLIENT_SECRET = 'client_secret';
    const KEY_GATEWAY_ORDER_DESCRIPTION = 'gateway_order_description';
    const KEY_TEST_MODE = 'test_mode';

    const KEY_TERMINAL_ID_TEST = 'terminal_id_test';
    const KEY_CLIENT_ID_TEST = 'client_id_test';
    const KEY_CLIENT_SECRET_TEST = 'client_secret_test';

    /**
     * DNA config constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param null|string $methodCode
     * @param string $pathPattern
     * @param Json|null $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    public function isActive($storeId = null) {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }
    public function getTerminalId($storeId = null) {
        return $this->getValue(Config::KEY_TERMINAL_ID, $storeId);
    }

    public function getClientId($storeId = null) {
        return $this->getValue(Config::KEY_CLIENT_ID, $storeId);
    }

    public function getClientSecret($storeId = null) {
        return $this->getValue(Config::KEY_CLIENT_SECRET, $storeId);
    }

    public function getGatewayOrderDescription($storeId = null) {
        return $this->getValue(Config::KEY_GATEWAY_ORDER_DESCRIPTION, $storeId);
    }

    public function getTestMode($storeId = null) {
        return (bool) $this->getValue(Config::KEY_TEST_MODE, $storeId);
    }

    public function getTerminalIdTest($storeId = null) {
      return $this->getValue(Config::KEY_TERMINAL_ID_TEST, $storeId);
    }

    public function getClientIdTest($storeId = null) {
        return $this->getValue(Config::KEY_CLIENT_ID_TEST, $storeId);
    }

    public function getClientSecretTest($storeId = null) {
        return $this->getValue(Config::KEY_CLIENT_SECRET_TEST, $storeId);
    }
}
