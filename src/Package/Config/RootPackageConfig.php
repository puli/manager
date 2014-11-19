<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Config;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\InvalidConfigException;

/**
 * The configuration of the root Puli package.
 *
 * The configuration inherits from a global configuration that needs to be
 * passed to {@link __construct()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageConfig extends PackageConfig
{
    /**
     * @var string[]
     */
    private $packageOrder = array();

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var GlobalConfig
     */
    private $localConfig;

    /**
     * Creates a new root package configuration.
     *
     * The configuration will inherit its settings from a system-wide
     * configuration that needs to be passed to the constructor.
     *
     * @param GlobalConfig $globalConfig The global configuration.
     * @param string|null  $packageName  The package name. Optional.
     */
    public function __construct(GlobalConfig $globalConfig, $packageName = null)
    {
        parent::__construct($packageName);

        $this->globalConfig = $globalConfig;
        $this->localConfig = new GlobalConfig();
    }

    /**
     * Returns the order in which some packages should be loaded.
     *
     * If packages contain conflicting resource definitions, this setting can be
     * used to specify in which order these packages should be loaded.
     *
     * @return string[] A list of package names.
     */
    public function getPackageOrder()
    {
        return $this->packageOrder;
    }

    /**
     * Sets the order in which some packages should be loaded.
     *
     * If packages contain conflicting resource definitions, this setting can be
     * used to specify in which order these packages should be loaded.
     *
     * @param string[] $packageOrder A list of package names.
     */
    public function setPackageOrder(array $packageOrder)
    {
        $this->packageOrder = $packageOrder;
    }

    /**
     * Returns the path to the package repository configuration file.
     *
     * @param bool $fallback Whether to fall back to the global configuration
     *                       value if no value was set. Defaults to `true`.
     *
     * @return string The path to the configuration file.
     *
     * @see GlobalConfig::getPackageRepositoryConfig()
     */
    public function getPackageRepositoryConfig($fallback = true)
    {
        $configPath = $this->localConfig->getPackageRepositoryConfig();

        if (null === $configPath && $fallback) {
            return $this->globalConfig->getPackageRepositoryConfig();
        }

        return $configPath;
    }

    /**
     * Sets the path to the package repository configuration file.
     *
     * This value will overshadow the value from the global configuration. You
     * can use {@link resetPackageRepositoryConfig()} to reset the value back to
     * the global value.
     *
     * @param string $configPath The path to the configuration file.
     *
     * @throws InvalidConfigException If the path is empty or not a string.
     *
     * @see GlobalConfig::setPackageRepositoryConfig()
     */
    public function setPackageRepositoryConfig($configPath)
    {
        $this->localConfig->setPackageRepositoryConfig($configPath);
    }

    /**
     * Resets the path to the package repository configuration file to the
     * global value.
     *
     * @see setPackageRepositoryConfig()
     */
    public function resetPackageRepositoryConfig()
    {
        $this->localConfig->setPackageRepositoryConfig(null);
    }

    /**
     * Returns the path where generated resource repository is placed.
     *
     * @param bool $fallback Whether to fall back to the global configuration
     *                       value if no value was set. Defaults to `true`.
     *
     * @return string The path to the generated resource repository.
     *
     * @see GlobalConfig::getGeneratedResourceRepository()
     */
    public function getGeneratedResourceRepository($fallback = true)
    {
        $repoPath = $this->localConfig->getGeneratedResourceRepository();

        if (null === $repoPath && $fallback) {
            return $this->globalConfig->getGeneratedResourceRepository();
        }

        return $repoPath;
    }

    /**
     * Sets the path where generated resource repository is placed.
     *
     * This value will overshadow the value from the global configuration. You
     * can use {@link resetGeneratedResourceRepository()} to reset the value
     * back to the global value.
     *
     * @param string $repoPath The path to the generated resource repository.
     *
     * @throws InvalidConfigException If the path is empty or not a string.
     *
     * @see GlobalConfig::setGeneratedResourceRepository()
     */
    public function setGeneratedResourceRepository($repoPath)
    {
        $this->localConfig->setGeneratedResourceRepository($repoPath);
    }

    /**
     * Resets the path where generated resource repository is placed to the
     * global value.
     *
     * @see setGeneratedResourceRepository()
     */
    public function resetGeneratedResourceRepository()
    {
        $this->localConfig->setGeneratedResourceRepository(null);
    }

    /**
     * Returns the path where the generated resource repository caches its files.
     *
     * @param bool $fallback Whether to fall back to the global configuration
     *                       value if no value was set. Defaults to `true`.
     *
     * @return string The path to the resource repository cache.
     *
     * @see GlobalConfig::getResourceRepositoryCache()
     */
    public function getResourceRepositoryCache($fallback = true)
    {
        $cachePath = $this->localConfig->getResourceRepositoryCache();

        if (null === $cachePath && $fallback) {
            return $this->globalConfig->getResourceRepositoryCache();
        }

        return $cachePath;
    }

    /**
     * Sets the path where the generated resource repository caches its files.
     *
     * This value will overshadow the value from the global configuration. You
     * can use {@link resetResourceRepositoryCache()} to reset the value
     * back to the global value.
     *
     * @param string $cachePath The path to the resource repository cache.
     *
     * @throws InvalidConfigException If the path is empty or not a string.
     *
     * @see GlobalConfig::setResourceRepositoryCache()
     */
    public function setResourceRepositoryCache($cachePath)
    {
        $this->localConfig->setResourceRepositoryCache($cachePath);
    }

    /**
     * Resets the path where generated resource repository caches its files to
     * the global value.
     *
     * @see setResourceRepositoryCache()
     */
    public function resetResourceRepositoryCache()
    {
        $this->localConfig->setResourceRepositoryCache(null);
    }

    /**
     * Returns the plugin classes.
     *
     * @param bool $fallback Whether to merge the plugin classes set in this
     *                       config with the classes from the global
     *                       configuration. Defaults to `true`.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see GlobalConfig::getPluginClasses()
     */
    public function getPluginClasses($fallback = true)
    {
        $pluginClasses = $this->localConfig->getPluginClasses();

        if ($fallback) {
            $pluginClasses = array_unique(array_merge(
                $this->globalConfig->getPluginClasses(),
                $pluginClasses
            ));
        }

        return $pluginClasses;
    }

    /**
     * Sets the plugin classes.
     *
     * The added classes are merged with the classes from the global
     * configuration. You can use {@link resetPluginClasses()} to reset the
     * plugins back to the ones from the global configuration.
     *
     * @param string[] $pluginClasses The fully qualified plugin class names.
     *
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\PackageManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     *
     * @see GlobalConfig::setPluginClasses()
     */
    public function setPluginClasses(array $pluginClasses)
    {
        $this->localConfig->setPluginClasses($pluginClasses);
    }

    /**
     * Adds a plugin class.
     *
     * The added classes are merged with the classes from the global
     * configuration. You can use {@link resetPluginClasses()} to reset the
     * plugins back to the ones from the global configuration.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If the class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\PackageManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     *
     * @see GlobalConfig::addPluginClass()
     */
    public function addPluginClass($pluginClass)
    {
        $this->localConfig->addPluginClass($pluginClass);
    }

    /**
     * Removes a plugin class.
     *
     * Classes are only removed from the package configuration, but not from
     * the global configuration. If the plugin class is not found in the package
     * configuration, this method does nothing.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @see GlobalConfig::removePluginClass()
     */
    public function removePluginClass($pluginClass)
    {
        $this->localConfig->removePluginClass($pluginClass);
    }

    /**
     * Resets the plugin classes to the classes defined in the global
     * configuration.
     *
     * @see setPluginClasses()
     */
    public function resetPluginClasses()
    {
        $this->localConfig->setPluginClasses(array());
    }
}
