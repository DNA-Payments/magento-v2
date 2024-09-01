<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dna\Payment\Model\Ui;

use Dna\Payment\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'dna_payment';
    const CC_VAULT_CODE = 'dna_payment_cc_vault';

    /**
     * @var Config
    */
    private $config;

    /**
     * @var SessionManagerInterface
     */
    private $session;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    public function __construct(
        Config $config,
        SessionManagerInterface $session,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $storeId = $this->session->getStoreId();
        $integrationType = $this->config->getIntegrationType($storeId);

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive($storeId),
                    'integrationType' => $integrationType,
                    'ccVaultCode' => self::CC_VAULT_CODE,
                ]
            ]
        ];
    }
}
