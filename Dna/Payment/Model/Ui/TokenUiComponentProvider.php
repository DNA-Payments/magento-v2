<?php

namespace Dna\Payment\Model\Ui;

use Dna\Payment\Helper\DnaLogger;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    private $componentFactory;
    protected $dnaLogger;

    public function __construct(
        DnaLogger $dnaLogger,
        TokenUiComponentInterfaceFactory $componentFactory
    )
    {
        $this->componentFactory = $componentFactory;
        $this->dnaLogger = $dnaLogger;
    }

    /**
     * Get UI component for token
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $component = $this->componentFactory->create(
            [
                'config' => [
                    'code' => ConfigProvider::CC_VAULT_CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Dna_Payment/js/view/payment/method-renderer/vault'
            ]
        );

        $this->dnaLogger->info('getComponentForToken', [
            'component' => $component,
        ]);

        return $component;
    }
}