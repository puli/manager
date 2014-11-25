<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Manager;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\GlobalConfigStorage;
use Puli\PackageManager\Environment\GlobalEnvironment;
use Puli\PackageManager\InvalidConfigException;

/**
 * Manages changes to the global configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalConfigManager
{
    /**
     * @var GlobalEnvironment
     */
    private $environment;

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var GlobalConfigStorage
     */
    private $configStorage;

    /**
     * Creates the configuration manager.
     *
     * @param GlobalEnvironment   $environment   The global environment.
     * @param GlobalConfigStorage $configStorage The configuration file storage.
     */
    public function __construct(GlobalEnvironment $environment, GlobalConfigStorage $configStorage)
    {
        $this->environment = $environment;
        $this->configStorage = $configStorage;
        $this->globalConfig = $environment->getGlobalConfig();
    }

    /**
     * Returns the global environment.
     *
     * @return GlobalEnvironment The global environment.
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Installs a plugin class in the global configuration.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link \Puli\PackageManager\Plugin\PluginInterface}. Plugin
     * constructors must not have mandatory parameters.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\PackageManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     */
    public function installGlobalPluginClass($pluginClass)
    {
        if ($this->globalConfig->hasPluginClass($pluginClass)) {
            // Already installed
            return;
        }

        $this->globalConfig->addPluginClass($pluginClass);

        $this->configStorage->saveGlobalConfig($this->globalConfig);
    }

    /**
     * Returns whether a plugin class is installed in the global configuration.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @return bool Whether the plugin class is installed.
     *
     * @see installGlobalPluginClass()
     */
    public function isGlobalPluginClassInstalled($pluginClass)
    {
        return $this->globalConfig->hasPluginClass($pluginClass);
    }

    /**
     * Returns all globally installed plugin classes.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see installGlobalPluginClass()
     */
    public function getGlobalPluginClasses()
    {
        return $this->globalConfig->getPluginClasses();
    }
}
