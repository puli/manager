<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Cache;

use Puli\Manager\Api\Cache\CacheFile;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Assert\Assert;
use stdClass;
use Webmozart\Json\Conversion\JsonConverter;

/**
 * Converts cache file from and to json.
 *
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
class CacheFileConverter implements JsonConverter
{
    /**
     * @var JsonConverter
     */
    private $moduleFileConverter;

    /**
     * Creates a new cache file converter.
     *
     * @param JsonConverter $moduleFileConverter The module file converter.
     */
    public function __construct(JsonConverter $moduleFileConverter)
    {
        $this->moduleFileConverter = $moduleFileConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson($cacheFile, array $options = array())
    {
        Assert::isInstanceOf($cacheFile, 'Puli\Manager\Api\Cache\CacheFile');

        $jsonData = new stdClass();
        $jsonData->modules = array();
        $jsonData->installInfos = array();

        foreach ($cacheFile->getModuleFiles() as $moduleFile) {
            $jsonData->modules[] = $this->moduleFileConverter->toJson($moduleFile, array(
                'targetVersion' => $moduleFile->getVersion(),
            ));
        }
        foreach ($cacheFile->getInstallInfos() as $installInfo) {
            $jsonData->installInfos[$installInfo->getModuleName()] = $installInfo->getInstallPath();
        }

        return $jsonData;
    }

    /**
     * {@inheritdoc}
     */
    public function fromJson($jsonData, array $options = array())
    {
        Assert::isInstanceOf($jsonData, 'stdClass');

        $cacheFile = new CacheFile(isset($options['path']) ? $options['path'] : null);

        foreach ($jsonData->modules as $module) {
            $moduleFile = $this->moduleFileConverter->fromJson($module);
            $cacheFile->addModuleFile($moduleFile);
        }

        foreach ($jsonData->installInfos as $packageName => $installPath) {
            $cacheFile->addInstallInfo(new InstallInfo($packageName, $installPath));
        }

        return $cacheFile;
    }
}
