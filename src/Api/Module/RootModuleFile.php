<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Module;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\InvalidConfigException;

/**
 * The module file of the root module.
 *
 * You can pass a base configuration to the constructor that the module's
 * configuration will inherit.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootModuleFile extends ModuleFile
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
     * Creates a new root module file.
     *
     * The file's configuration will inherit its settings from the base
     * configuration passed to the constructor.
     *
     * @param string|null $moduleName The module name. Optional.
     * @param string|null $path       The path where the configuration is
     *                                stored or `null` if this configuration is
     *                                not stored on the file system.
     * @param Config|null $baseConfig The configuration that the module will
     *                                inherit its configuration values from.
     */
    public function __construct($moduleName = null, $path = null, Config $baseConfig = null)
    {
        parent::__construct($moduleName, $path);

        $this->config = new Config($baseConfig);
    }

    /**
     * Returns the configuration of the module.
     *
     * @return Config The configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns the order in which modules should be loaded.
     *
     * If modules contain path mappings for the same resource paths, this
     * setting can be used to specify in which order these modules should be
     * loaded. Alternatively, you can use {@link setOverriddenModules()} to
     * mark one of the modules to override the other one.
     *
     * @return string[] A list of module names.
     */
    public function getOverrideOrder()
    {
        return $this->overrideOrder;
    }

    /**
     * Sets the order in which modules should be loaded.
     *
     * If modules contain path mappings for the same resource paths, this
     * setting can be used to specify in which order these modules should be
     * loaded. Alternatively, you can use {@link setOverriddenModules()} to
     * mark one of the modules to override the other one.
     *
     * @param string[] $overrideOrder A list of module names.
     */
    public function setOverrideOrder(array $overrideOrder)
    {
        $this->overrideOrder = $overrideOrder;
    }

    /**
     * Sets the install infos of all installed modules.
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
     * Adds install info for an installed module.
     *
     * @param InstallInfo $installInfo The install info.
     */
    public function addInstallInfo(InstallInfo $installInfo)
    {
        $this->installInfos[$installInfo->getModuleName()] = $installInfo;
    }

    /**
     * Removes the install info of an installed module.
     *
     * @param string $moduleName The module name.
     */
    public function removeInstallInfo($moduleName)
    {
        unset($this->installInfos[$moduleName]);
    }

    /**
     * Removes all install infos.
     */
    public function clearInstallInfos()
    {
        $this->installInfos = array();
    }

    /**
     * Returns the install info of an installed module.
     *
     * @param string $moduleName The module name.
     *
     * @return InstallInfo The install info.
     *
     * @throws NoSuchModuleException If no module is installed with that name.
     */
    public function getInstallInfo($moduleName)
    {
        if (!isset($this->installInfos[$moduleName])) {
            throw new NoSuchModuleException(sprintf(
                'Could not get install info: The module "%s" is not installed.',
                $moduleName
            ));
        }

        return $this->installInfos[$moduleName];
    }

    /**
     * Returns the install infos of all installed modules.
     *
     * @return InstallInfo[] The install infos.
     */
    public function getInstallInfos()
    {
        // The module names as array keys are for internal use only
        return array_values($this->installInfos);
    }

    /**
     * Returns whether an install info with a given name exists.
     *
     * @param string $moduleName The name of the module.
     *
     * @return bool Whether install info with that name exists.
     */
    public function hasInstallInfo($moduleName)
    {
        return isset($this->installInfos[$moduleName]);
    }

    /**
     * Returns whether the module file contains any install infos.
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
