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
use Magento\Framework\UrlInterface;

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
    public function getConfig() {
        $storeId = $this->session->getStoreId();

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive($storeId),
                    'terminal_id' => $this->config->getTestMode($storeId) ? $this->config->getTerminalIdTest($storeId) : $this->config->getTerminalId($storeId),
                    'client_id' => $this->config->getTestMode($storeId) ? $this->config->getClientIdTest($storeId) : $this->config->getClientId($storeId),
                    'client_secret' => $this->config->getTestMode($storeId) ? $this->config->getClientSecretTest($storeId) : $this->config->getClientSecret($storeId),
                    'gateway_order_description' => $this->config->getGatewayOrderDescription($storeId),
                    'test_mode' => $this->config->getTestMode($storeId),
                    'back_link' => $this->config->getBackLink($storeId) ? $this->urlBuilder->getUrl($this->config->getBackLink($storeId)) : $this->urlBuilder->getUrl('checkout/onepage/success'),
                    'failure_back_link' => $this->config->getFailureBackLink($storeId) ? $this->urlBuilder->getUrl($this->config->getFailureBackLink($storeId)) : $this->urlBuilder->getUrl('dna/result/failure'),
                    'confirm_link' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/confirm'),
                    'close_link' => $this->urlBuilder->getUrl('rest/default/V1/dna-payment/close')
                ]
            ]
        ];
    }
}