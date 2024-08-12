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
     * @param string $moduleName
     * @return string
     */
    public function getModuleVersion(string $moduleName): string
    {
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        $composerFilePath = $path . '/composer.json';

        if ($this->fileDriver->isExists($composerFilePath)) {
            $composerJsonData = $this->fileDriver->fileGetContents($composerFilePath);
            $data = json_decode($composerJsonData);

            if (empty($data->version)) {
                $this->dnaLogger->warning('composer.json file does not contain version.', ['file' => $composerFilePath]);
            } else {
                return $data->version;
            }
        } else {
            $this->dnaLogger->warning('composer.json file not found.', ['file' => $composerFilePath]);
        }

        return 'unknown';
    }
}
