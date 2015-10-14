<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\InvalidConfigException;

/**
 * The package file of the root package.
 *
 * You can pass a base configuration to the constructor that the package's
 * configuration will inherit.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFile extends PackageFile
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var string[]
     */
    private $overrideOrder = array();

    /**
     * @var InstallInfo[]
     */
    private $installInfos = array();

    /**
     * @var string[]
     */
    private $pluginClasses = array();

    /**
     * Creates a new root package file.
     *
     * The file's configuration will inherit its settings from the base
     * configuration passed to the constructor.
     *
     * @param string|null $packageName The package name. Optional.
     * @param string|null $path        The path where the configuration is
     *                                 stored or `null` if this configuration is
     *                                 not stored on the file system.
     * @param Config|null $baseConfig  The configuration that the package will
     *                                 inherit its configuration values from.
     */
    public function __construct($packageName = null, $path = null, Config $baseConfig = null)
    {
        parent::__construct($packageName, $path);

        $this->config = new Config($baseConfig);
    }

    /**
     * Returns the configuration of the package.
     *
     * @return Config The configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns the order in which packages should be loaded.
     *
     * If packages contain path mappings for the same resource paths, this
     * setting can be used to specify in which order these packages should be
     * loaded. Alternatively, you can use {@link setOverriddenPackages()} to
     * mark one of the packages to override the other one.
     *
     * @return string[] A list of package names.
     */
    public function getOverrideOrder()
    {
        return $this->overrideOrder;
    }

    /**
     * Sets the order in which packages should be loaded.
     *
     * If packages contain path mappings for the same resource paths, this
     * setting can be used to specify in which order these packages should be
     * loaded. Alternatively, you can use {@link setOverriddenPackages()} to
     * mark one of the packages to override the other one.
     *
     * @param string[] $overrideOrder A list of package names.
     */
    public function setOverrideOrder(array $overrideOrder)
    {
        $this->overrideOrder = $overrideOrder;
    }

    /**
     * Sets the install infos of all installed packages.
     *
     * @param InstallInfo[] The install infos.
     */
    public function setInstallInfos(array $installInfos)
    {
        $this->installInfos = array();

        foreach ($installInfos as $installInfo) {
            $this->addInstallInfo($installInfo);
        }
    }

    /**
     * Adds install info for an installed package.
     *
     * @param InstallInfo $installInfo The install info.
     */
    public function addInstallInfo(InstallInfo $installInfo)
    {
        $this->installInfos[$installInfo->getPackageName()] = $installInfo;
    }

    /**
     * Removes the install info of an installed package.
     *
     * @param string $packageName The package name.
     */
    public function removeInstallInfo($packageName)
    {
        unset($this->installInfos[$packageName]);
    }

    /**
     * Removes all install infos.
     */
    public function clearInstallInfos()
    {
        $this->installInfos = array();
    }

    /**
     * Returns the install info of an installed package.
     *
     * @param string $packageName The package name.
     *
     * @return InstallInfo The install info.
     *
     * @throws NoSuchPackageException If no package is installed with that name.
     */
    public function getInstallInfo($packageName)
    {
        if (!isset($this->installInfos[$packageName])) {
            throw new NoSuchPackageException(sprintf(
                'Could not get install info: The package "%s" is not installed.',
                $packageName
            ));
        }

        return $this->installInfos[$packageName];
    }

    /**
     * Returns the install infos of all installed packages.
     *
     * @return InstallInfo[] The install infos.
     */
    public function getInstallInfos()
    {
        // The package names as array keys are for internal use only
        return array_values($this->installInfos);
    }

    /**
     * Returns whether an install info with a given name exists.
     *
     * @param string $packageName The name of the package.
     *
     * @return bool Whether install info with that name exists.
     */
    public function hasInstallInfo($packageName)
    {
        return isset($this->installInfos[$packageName]);
    }

    /**
     * Returns whether the package file contains any install infos.
     *
     * @return bool Returns `true` if the file contains install infos and
     *              `false` otherwise.
     */
    public function hasInstallInfos()
    {
        return count($this->installInfos) > 0;
    }

    /**
     * Returns the plugin classes.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see setPluginClasses()
     */
    public function getPluginClasses()
    {
        return array_keys($this->pluginClasses);
    }

    /**
     * Sets the plugin classes.
     *
     * The plugin classes must be fully-qualified class names that implement
     * {@link Puli\Manager\Api\PuliPlugin}. If a class is not
     * found or does not implement that interface, an exception is thrown.
     *
     * The plugin classes must not have required parameters in their constructor
     * so that they can be successfully instantiated. If a constructor has
     * required parameters, an exception is thrown.
     *
     * Leading backslashes are removed from the fully-qualified class names.
     *
     * @param string[] $pluginClasses The fully qualified plugin class names.
     *
     * @throws InvalidConfigException If the class is not found, is not a class,
     *                                does not implement {@link PuliPlugin}
     *                                or has required constructor parameters.
     */
    public function setPluginClasses(array $pluginClasses)
    {
        $this->pluginClasses = array();

        $this->addPluginClasses($pluginClasses);
    }

    /**
     * Adds multiple plugin classes.
     *
     * The plugin classes must be fully-qualified class names that implement
     * {@link Puli\Manager\Api\PuliPlugin}. If a class is not
     * found or does not implement that interface, an exception is thrown.
     *
     * The plugin classes must not have required parameters in their constructor
     * so that they can be successfully instantiated. If a constructor has
     * required parameters, an exception is thrown.
     *
     * Leading backslashes are removed from the fully-qualified class names.
     *
     * @param string[] $pluginClasses The fully qualified plugin class names.
     */
    public function addPluginClasses(array $pluginClasses)
    {
        foreach ($pluginClasses as $pluginClass) {
            $this->addPluginClass($pluginClass);
        }
    }

    /**
     * Adds a plugin class.
     *
     * The plugin class must be a fully-qualified class name that implements
     * {@link PuliPlugin}. If the class is not found or does not implement
     * that interface, an exception is thrown.
     *
     * The plugin class must not have required parameters in its constructor
     * so that it can be successfully instantiate. If the constructor has
     * required parameters, an exception is thrown.
     *
     * Leading backslashes are removed from the fully-qualified class name.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     */
    public function addPluginClass($pluginClass)
    {
        $this->pluginClasses[ltrim($pluginClass, '\\')] = true;
    }

    /**
     * Removes a plugin class.
     *
     * If the plugin class has not been added, this method does nothing. This
     * method also does not validate whether the passed value is actually a
     * plugin class.
     *
     * Leading backslashes are removed from the fully-qualified class name.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     */
    public function removePluginClass($pluginClass)
    {
        unset($this->pluginClasses[ltrim($pluginClass, '\\')]);
    }

    /**
     * Removes all plugin classes.
     */
    public function clearPluginClasses()
    {
        $this->pluginClasses = array();
    }

    /**
     * Returns whether the configuration contains a plugin class.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @return bool Whether the configuration contains the plugin class.
     */
    public function hasPluginClass($pluginClass)
    {
        return isset($this->pluginClasses[ltrim($pluginClass, '\\')]);
    }

    /**
     * Returns whether the configuration contains any plugin classes.
     *
     * @return bool Whether the configuration contains any plugin classes.
     */
    public function hasPluginClasses()
    {
        return count($this->pluginClasses) > 0;
    }
}
