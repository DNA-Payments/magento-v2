<?php

namespace Dna\Payment\Model\ApplePay;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'active';

    /**
     * @var \Dna\Payment\Gateway\Config\Config
     */
    protected $dnaPaymentConfig;

    /**
     * Config constructor.
     * @param \Dna\Payment\Gateway\Config\Config $dnaPaymentConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        \Dna\Payment\Gateway\Config\Config $dnaPaymentConfig,
        ScopeConfigInterface $scopeConfig,
        $methodCode = null,
        string $pathPattern = \Magento\Payment\Gateway\Config\Config::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->dnaPaymentConfig = $dnaPaymentConfig;
    }

    /**
     * Get Payment configuration status
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE);
    }
}
