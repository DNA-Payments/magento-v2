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
     * A file is used only if it is valid JSON, is named dna/magento-2 and has a version;
     * otherwise the next place is checked.
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

        $candidates = [$path . '/composer.json', dirname($path, 2) . '/composer.json'];

        foreach ($candidates as $composerFilePath) {
            if (!$this->fileDriver->isExists($composerFilePath)) {
                continue;
            }

            $data = json_decode($this->fileDriver->fileGetContents($composerFilePath));

            if (!is_object($data) || ($data->name ?? null) !== 'dna/magento-2' || empty($data->version)) {
                continue;
            }

            return $data->version;
        }

        $this->dnaLogger->warning('Plugin version not found in composer.json.', ['paths' => $candidates]);
        return 'unknown';
    }
}
