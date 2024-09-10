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
use Magento\Payment\Model\CcConfig;
use Magento\Framework\View\Asset\Source;

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
    private $ccConfig;
    private $assetSource;

    /**
     * @var SessionManagerInterface
     */
    private $session;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;
    private $icons = [];
    private $ccMapping = [
        'american-express' => 'ae',
        'discover' => 'di',
        'diners-club' => 'dn',
        'elo' => 'elo',
        'hipercard' => 'hc',
        'hiper' => 'hc',
        'jcb' => 'jcb',
        'mastercard' => 'mc',
        'maestro' => 'sm',
        'unionpay' => 'un',
        'visa' => 'vi',
        'none' => 'none',
    ];

    public function __construct(
        Config                  $config,
        SessionManagerInterface $session,
        UrlInterface            $urlBuilder,
        CcConfig                $ccConfig,
        Source                  $assetSource
    )
    {
        $this->config = $config;
        $this->session = $session;
        $this->urlBuilder = $urlBuilder;
        $this->ccConfig = $ccConfig;
        $this->assetSource = $assetSource;
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
                    'icons' => $this->getIcons()
                ]
            ]
        ];
    }

    public function getIcons(): array
    {
        if (!empty($this->icons)) {
            return $this->icons;
        }

        foreach ($this->ccMapping as $cardType => $fileCode) {
            if (!array_key_exists($cardType, $this->icons)) {
                $asset = $this->ccConfig->createAsset('Dna_Payment::images/cc/' . $fileCode . '.png');
                if ($asset) {
                    $placeholder = $this->assetSource->findSource($asset);
                    if ($placeholder) {
                        list($width, $height) = getimagesize($asset->getSourceFile());
                        $this->icons[$cardType] = [
                            'url' => $asset->getUrl(),
                            'width' => $width,
                            'height' => $height
                        ];
                    }
                }
            }
        }

        return $this->icons;
    }
}
