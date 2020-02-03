<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dna\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Dna\Payment\Gateway\Http\Client\ClientMock;
use Dna\Payment\Gateway\Config\use Dna\Payment\Gateway\Config\ConfigConfig;
;
use Magento\Framework\Session\SessionManagerInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'dna_payment';

    private $config;
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
    public function getConfig()
    {
        $storeId = $this->session->getStoreId();

        return [
            'payment' => [
                self::CODE => [
                    'terminalId' => $this->config->getTerminalId($storeId),
                ]
            ]
        ];
    }
}
