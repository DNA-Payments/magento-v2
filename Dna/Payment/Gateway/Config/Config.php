<?php

namespace Dna\Payment\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;


class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'active';
    const KEY_TERMINAL_ID = 'terminal_id';
    const KEY_CLIENT_ID = 'client_id';
    const KEY_CLIENT_SECRET = 'client_secret';

    /**
     * Braintree config constructor
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

}
