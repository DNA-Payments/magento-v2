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
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'terminalId' => $this->config->getTerminalId()
                ]
            ]
        ];
    }
}
