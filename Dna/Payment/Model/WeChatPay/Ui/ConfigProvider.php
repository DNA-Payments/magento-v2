<?php
namespace Dna\Payment\Model\WeChatPay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Dna\Payment\Model\WeChatPay\Config;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'dna_payment_wechatpay';

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
