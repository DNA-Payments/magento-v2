<?php
namespace Dna\Payment\Model\Alipay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Dna\Payment\Model\Alipay\Config;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'dna_payment_alipay_plus';

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
