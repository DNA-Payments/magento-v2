<?php

namespace Dna\Payment\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;


class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'active';
    const TERMINAL_ID = 'terminal_id';

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



    /**
     * Gets terminal ID.
     *
     * @param int|null $storeId
          * @return string
          */
     public function getTerminalId() {
         return $this->getValue(Config::TERMINAL_ID);
     }

}
