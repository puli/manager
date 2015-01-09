<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile;

use ArrayIterator;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\AbstractConfigFileManager;
use Puli\RepositoryManager\Config\NoSuchConfigKeyException;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\IOException;
use Webmozart\Glob\Iterator\GlobFilterIterator;
use Webmozart\Glob\Iterator\RegexFilterIterator;

/**
 * Manages changes to the root package file.
 *
 * Use this class to make persistent changes to the puli.json of a project.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFileManager extends AbstractConfigFileManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * Creates a new package file manager.
     *
     * @param ProjectEnvironment $environment        The project environment
     * @param PackageFileStorage $packageFileStorage The package file storage.
     */
    public function __construct(ProjectEnvironment $environment, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->packageFileStorage = $packageFileStorage;
    }

    /**
     * Returns the project environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Returns the managed package file.
     *
     * @return RootPackageFile The managed package file.
     */
    public function getPackageFile()
    {
        return $this->rootPackageFile;
    }

    /**
     * Returns the package name configured in the package file.
     *
     * @return null|string The configured package name.
     */
    public function getPackageName()
    {
        return $this->rootPackageFile->getPackageName();
    }

    /**
     * Sets the package name configured in the package file.
     *
     * @param string $packageName The package name.
     */
    public function setPackageName($packageName)
    {
        if ($packageName !== $this->rootPackageFile->getPackageName()) {
            $this->rootPackageFile->setPackageName($packageName);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        }
    }

    /**
     * Installs a plugin class.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link ManagerPlugin}. Plugin constructors must not have
     * mandatory parameters.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement {@link ManagerPlugin}
     *                                or has required constructor parameters.
     */
    public function installPluginClass($pluginClass)
    {
        if ($this->rootPackageFile->hasPluginClass($pluginClass)) {
            // Already installed locally
            return;
        }

        $this->rootPackageFile->addPluginClass($pluginClass);

        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Returns whether a plugin class is installed.
     *
     * @param string $pluginClass   The fully qualified plugin class name.
     * @param bool   $includeGlobal If set to `true`, both plugins installed in
     *                              the configuration of the root package and
     *                              plugins installed in the global configuration
     *                              are considered. If set to `false`, only the
     *                              plugins defined in the root package are
     *                              considered.
     *
     * @return bool Whether the plugin class is installed.
     *
     * @see installPluginClass()
     */
    public function isPluginClassInstalled($pluginClass, $includeGlobal = true)
    {
        return $this->rootPackageFile->hasPluginClass($pluginClass, $includeGlobal);
    }

    /**
     * Returns all installed plugin classes.
     *
     * @param bool $includeGlobal If set to `true`, both plugins installed in
     *                            the configuration of the root package and
     *                            plugins installed in the global configuration
     *                            are returned. If set to `false`, only the
     *                            plugins defined in the root package are
     *                            returned.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see installPluginClass()
     */
    public function getPluginClasses($includeGlobal = true)
    {
        return $this->rootPackageFile->getPluginClasses($includeGlobal);
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfig()
    {
        return $this->rootPackageFile->getConfig();
    }

    /**
     * {@inheritdoc}
     */
    protected function saveConfigFile()
    {
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }
}
