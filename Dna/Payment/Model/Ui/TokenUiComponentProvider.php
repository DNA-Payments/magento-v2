<?php

namespace Dna\Payment\Model\Ui;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    /**
     * @var TokenUiComponentInterfaceFactory
     */
    private $componentFactory;
    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        EncryptorInterface $encryptor
    )
    {
        $this->componentFactory = $componentFactory;
        $this->encryptor = $encryptor;
    }

    /**
     * Get UI component for token
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $encryptedToken = $paymentToken->getGatewayToken();
        $cardTokenId = $this->encryptor->decrypt($encryptedToken);
        $component = $this->componentFactory->create(
            [
                'config' => [
                    'code' => ConfigProvider::CC_VAULT_CODE,
                    'merchantTokenId' => $cardTokenId,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Dna_Payment/js/view/payment/method-renderer/vault'
            ]
        );

        return $component;
    }
}