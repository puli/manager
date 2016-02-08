<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Cache;

use InvalidArgumentException;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Assert\Assert;

/**
 * Stores combined Modules configuration.
 *
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
class CacheFile
{
    /**
     * @var string|null
     */
    private $path;

    /**
     * @var ModuleFile[]
     */
    private $moduleFiles = array();

    /**
     * @var InstallInfo[]
     */
    private $installInfos = array();

    /**
     * Creates new CacheFile.
     *
     * @param string|null $path The path where the cache file is stored.
     */
    public function __construct($path = null)
    {
        Assert::nullOrAbsoluteSystemPath($path);

        $this->path = $path;
    }

    /**
     * Returns the path to the cache file.
     *
     * @return string|null The path or `null` if this file is not stored on the
     *                     file system.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets the module files.
     *
     * @param ModuleFile[] $moduleFiles The module files.
     */
    public function setModuleFiles(array $moduleFiles)
    {
        $this->moduleFiles = array();

        foreach ($moduleFiles as $moduleFile) {
            $this->addModuleFile($moduleFile);
        }
    }

    /**
     * Adds a module to the cache file.
     *
     * @param ModuleFile $moduleFile The added module file.
     */
    public function addModuleFile(ModuleFile $moduleFile)
    {
        $this->moduleFiles[$moduleFile->getModuleName()] = $moduleFile;

        ksort($this->moduleFiles);
    }

    /**
     * Removes a module file from the cache file.
     *
     * @param string $moduleName The module name.
     */
    public function removeModuleFile($moduleName)
    {
        unset($this->moduleFiles[$moduleName]);
    }

    /**
     * Removes all module files from the cache file.
     */
    public function clearModuleFiles()
    {
        $this->moduleFiles = array();
    }

    /**
     * Returns the module file with the given name.
     *
     * @param string $moduleName The module name.
     *
     * @return ModuleFile The module with the passed name.
     *
     * @throws InvalidArgumentException If the module was not found.
     */
    public function getModuleFile($moduleName)
    {
        Assert::moduleName($moduleName);

        if (!isset($this->moduleFiles[$moduleName])) {
            throw new InvalidArgumentException(sprintf('Could not find a module named %s.', $moduleName));
        }

        return $this->moduleFiles[$moduleName];
    }

    /**
     * Returns the module files in the cache file.
     *
     * @return ModuleFile[] The module files in the cache file.
     */
    public function getModuleFiles()
    {
        return $this->moduleFiles;
    }

    /**
     * Returns whether a module with the given name exists.
     *
     * @param string $moduleName The module name.
     *
     * @return bool Whether a module with this name exists.
     */
    public function hasModuleFile($moduleName)
    {
        return isset($this->moduleFiles[$moduleName]);
    }

    /**
     * Returns whether a cache file contains any module files.
     *
     * @return bool Whether a cache file contains any module files.
     */
    public function hasModuleFiles()
    {
        return count($this->moduleFiles) > 0;
    }

    /**
     * Adds install info to the cache file.
     *
     * @param InstallInfo $installInfo The module install info.
     */
    public function addInstallInfo(InstallInfo $installInfo)
    {
        $this->installInfos[$installInfo->getModuleName()] = $installInfo;

        ksort($this->installInfos);
    }

    /**
     * Removes install info file from the cache file.
     *
     * @param string $moduleName The module name.
     */
    public function removeInstallInfo($moduleName)
    {
        unset($this->installInfos[$moduleName]);
    }

    /**
     * Removes all module install info from the cache file.
     */
    public function clearInstallInfo()
    {
        $this->installInfos = array();
    }

    /**
     * Returns the install info with the given module name.
     *
     * @param string $moduleName The module name.
     *
     * @return InstallInfo The module install info with the passed name.
     *
     * @throws InvalidArgumentException If the install info was not found.
     */
    public function getInstallInfo($moduleName)
    {
        Assert::moduleName($moduleName);

        if (!isset($this->installInfos[$moduleName])) {
            throw new InvalidArgumentException(sprintf('Could not find a module named %s.', $moduleName));
        }

        return $this->installInfos[$moduleName];
    }

    /**
     * Returns the install info for all Modules in the cache file.
     *
     * @return InstallInfo[] The install info for all Modules in the cache file.
     */
    public function getInstallInfos()
    {
        return $this->installInfos;
    }

    /**
     * Returns whether install info for a module with the given name exists.
     *
     * @param string $moduleName The module name.
     *
     * @return bool Whether install info for a module with this name exists.
     */
    public function hasInstallInfo($moduleName)
    {
        return isset($this->installInfos[$moduleName]);
    }

    /**
     * Returns whether a cache file contains any module install info.
     *
     * @return bool Whether a cache file contains any module install info.
     */
    public function hasInstallInfos()
    {
        return count($this->installInfos) > 0;
    }
}
