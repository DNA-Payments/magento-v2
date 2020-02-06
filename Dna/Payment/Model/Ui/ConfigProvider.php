<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dna\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Dna\Payment\Gateway\Http\Client\ClientMock;
use Dna\Payment\Gateway\Config\Config;
use Magento\Framework\Session\SessionManagerInterface;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'dna_payment';

    /**
     * @var Config
    */
    private $config;

   /**
    * @var SessionManagerInterface
    */
    private $session;

    public function __construct(
            Config $config,
            SessionManagerInterface $session
    ) {
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig() {
        $storeId = $this->session->getStoreId();

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive($storeId),
                    'terminal_id' => $this->config->getTestMode($storeId) ? $this->config->getTerminalIdTest($storeId) : $this->config->getTerminalId($storeId),
                    'client_id' => $this->config->getTestMode($storeId) ? $this->config->getClientIdTest($storeId) : $this->config->getClientId($storeId),
                    'client_secret' => $this->config->getTestMode($storeId) ? $this->config->getClientSecretTest($storeId) : $this->config->getClientSecret($storeId),
                    'description' => $this->config->getDescription($storeId),
                    'test_mode' => $this->config->getTestMode($storeId),
                    'js_url_test' => $this->config->getJsUrlTest($storeId)
                ]
            ]
        ];
    }
}
