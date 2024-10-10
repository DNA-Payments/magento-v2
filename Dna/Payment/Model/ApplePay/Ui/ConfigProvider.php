<?php
namespace Dna\Payment\Model\ApplePay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Dna\Payment\Model\ApplePay\Config;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'dna_payment_applepay';

    private $config;

    /**
     * ConfigProvider constructor.
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        if (!$this->config->isActive()) {
            return [];
        }

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive(),
                ]
            ]
        ];
    }
}
