<?php

namespace Dna\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ProductMetadataInterface;


class DnaAnalytics extends AbstractHelper
{
    protected $fileDriver;
    protected $productMetadata;
    protected $moduleVersionHelper;

    public function __construct(
        Context $context,
        File $fileDriver,
        ProductMetadataInterface $productMetadata,
        DnaModuleVersion $moduleVersionHelper
    ) {
        parent::__construct($context);
        $this->fileDriver = $fileDriver;
        $this->productMetadata = $productMetadata;
        $this->moduleVersionHelper = $moduleVersionHelper;
    }

    public function getAnalyticsData()
    {
        return [
            'phpVersion' => phpversion(),
            'domainName' => $_SERVER['HTTP_HOST'],
            'pluginVersion' => $this->moduleVersionHelper->getModuleVersion('Dna_Payment'),
            'cmsPlatformName' => $this->productMetadata->getName() . ' ' . $this->productMetadata->getEdition(),
            'cmsPlatformVersion' => $this->getMagentoVersion(),
        ];
    }

    protected function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    public function getCurrentHash()
    {
        $data = $this->getAnalyticsData();
        return hash('sha256', json_encode($data));
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function hasHashChanged()
    {
        $hashFile = $this->getHashFilePath();

        if (!$this->fileDriver->isExists($hashFile)) {
            $this->createHashFile($hashFile);
            return true;
        }

        $previousHash = file_get_contents($hashFile);
        return $this->getCurrentHash() !== $previousHash;
    }

    public function updateHash()
    {
        $hashFile = $this->getHashFilePath();
        file_put_contents($hashFile, $this->getCurrentHash());
    }

    protected function getHashFilePath()
    {
        return BP . '/var/dna_payment/analytics_hash.txt';
    }

    protected function createHashFile($filePath)
    {
        try {
            $directoryPath = dirname($filePath);
            if (!$this->fileDriver->isExists($directoryPath)) {
                $this->fileDriver->createDirectory($directoryPath, 0755);
            }
            $this->fileDriver->filePutContents($filePath, $this->getCurrentHash());
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to create hash file: %1', $e->getMessage()));
        }
    }
}
