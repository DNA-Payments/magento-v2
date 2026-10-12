<?php

namespace Dna\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\ModuleListInterface;

class DnaModuleVersion extends AbstractHelper
{
    /**
     * @var File
     */
    protected $fileDriver;

    /**
     * @var ComponentRegistrar
     */
    protected $componentRegistrar;
    protected $dnaLogger;
    protected $moduleList;

    public function __construct(
        Context $context,
        File $fileDriver,
        ComponentRegistrar $componentRegistrar,
        DnaLogger $dnaLogger,
        ModuleListInterface $moduleList
    ) {
        parent::__construct($context);
        $this->fileDriver = $fileDriver;
        $this->componentRegistrar = $componentRegistrar;
        $this->dnaLogger = $dnaLogger;
        $this->moduleList = $moduleList;
    }

    /**
     * Get module version from composer.json
     *
     * The module is registered from Dna/Payment, while the package composer.json
     * sits two levels up (vendor/dna/magento-2/composer.json), so both places are checked.
     *
     * @param string $moduleName
     * @return string
     */
    public function getModuleVersion(string $moduleName): string
    {
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        if (!$path) {
            return 'unknown';
        }

        foreach ([$path . '/composer.json', dirname($path, 2) . '/composer.json'] as $composerFilePath) {
            if (!$this->fileDriver->isExists($composerFilePath)) {
                continue;
            }

            $data = json_decode($this->fileDriver->fileGetContents($composerFilePath));
            if (isset($data->name) && $data->name !== 'dna/magento-2') {
                continue;
            }

            if (!empty($data->version)) {
                return $data->version;
            }

            $this->dnaLogger->warning('composer.json file does not contain version.', ['file' => $composerFilePath]);
            return 'unknown';
        }

        $this->dnaLogger->warning('composer.json file not found.', ['path' => $path]);
        return 'unknown';
    }
}
