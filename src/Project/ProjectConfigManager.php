<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Project;

use Puli\PackageManager\Config\GlobalConfigManager;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\RootPackageConfig;

/**
 * Manages changes to the project configuration.
 *
 * Use this class to make persistent changes to the puli.json of a project.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectConfigManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var RootPackageConfig
     */
    private $rootPackageConfig;

    /**
     * @var PackageConfigStorage
     */
    private $configStorage;

    /**
     * @var GlobalConfigManager
     */
    private $globalConfigManager;

    /**
     * Creates a new configuration manager.
     *
     * @param ProjectEnvironment   $environment         The project environment
     * @param PackageConfigStorage $configStorage       The package config file storage.
     * @param GlobalConfigManager  $globalConfigManager The global configuration manager.
     */
    public function __construct(ProjectEnvironment $environment, PackageConfigStorage $configStorage, GlobalConfigManager $globalConfigManager)
    {
        $this->environment = $environment;
        $this->rootPackageConfig = $environment->getRootPackageConfig();
        $this->configStorage = $configStorage;
        $this->globalConfigManager = $globalConfigManager;
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
     * Installs a plugin class.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link \Puli\PackageManager\Plugin\PluginInterface}. Plugin
     * constructors must not have mandatory parameters.
     *
     * By default, plugins are installed in the configuration of the root
     * package. Set the parameter `$global` to `true` if you want to install the
     * plugin globally.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     * @param bool   $global      Whether to install the plugin system-wide.
     *                            Defaults to `false?.
     *
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\PackageManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     */
    public function installPluginClass($pluginClass, $global = false)
    {
        if ($this->globalConfigManager->isGlobalPluginClassInstalled($pluginClass)) {
            // Already installed globally
            return;
        }

        if ($global) {
            $this->globalConfigManager->installGlobalPluginClass($pluginClass);

            return;
        }

        if ($this->rootPackageConfig->hasPluginClass($pluginClass)) {
            // Already installed locally
            return;
        }

        $this->rootPackageConfig->addPluginClass($pluginClass);

        $this->configStorage->savePackageConfig($this->rootPackageConfig);
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
        return $this->rootPackageConfig->hasPluginClass($pluginClass, $includeGlobal);
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
        return $this->rootPackageConfig->getPluginClasses($includeGlobal);
    }
}
