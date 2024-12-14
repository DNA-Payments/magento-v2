<?php
namespace Dna\Payment\Model\ApplePay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Dna\Payment\Model\ApplePay\Config;
use Magento\Framework\View\Asset\Repository;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'dna_payment_applepay';

    private $config;
    private $assetRepo;

    /**
     * ConfigProvider constructor.
     * @param Config $config
     * @param Repository $assetRepo
     */
    public function __construct(
        Config $config,
        Repository $assetRepo
    ) {
        $this->config = $config;
        $this->assetRepo = $assetRepo;
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
                    'logo' => $this->getPaymentLogo()
                ]
            ]
        ];
    }

    public function getPaymentLogo() {
        return $this->assetRepo->getUrl('Dna_Payment::images/apple_pay.svg');
    }
}
