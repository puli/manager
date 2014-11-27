<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\InvalidConfigException;

/**
 * The package file of the root package.
 *
 * You can pass a base configuration to the constructor that the package's
 * configuration will inherit.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageFile extends PackageFile
{
    /**
     * @var string[]
     */
    private $packageOrder = array();

    /**
     * @var Config
     */
    private $config;

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
     * @param Config      $baseConfig  The configuration that the package will
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
     * {@link \Puli\RepositoryManager\Plugin\PluginInterface}. If a class is not
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
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\RepositoryManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     */
    public function setPluginClasses(array $pluginClasses)
    {
        $this->pluginClasses = array();

        foreach ($pluginClasses as $pluginClass) {
            $this->addPluginClass($pluginClass);
        }
    }

    /**
     * Adds a plugin class.
     *
     * The plugin class must be a fully-qualified class name that implements
     * {@link \Puli\RepositoryManager\Plugin\PluginInterface}. If the class is not
     * found or does not implement that interface, an exception is thrown.
     *
     * The plugin class must not have required parameters in its constructor
     * so that it can be successfully instantiate. If the constructor has
     * required parameters, an exception is thrown.
     *
     * Leading backslashes are removed from the fully-qualified class name.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If the class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\RepositoryManager\Plugin\PluginInterface}
     *                                or has required constructor parameters.
     */
    public function addPluginClass($pluginClass)
    {
        try {
            $reflClass = new \ReflectionClass($pluginClass);
        } catch (\ReflectionException $e) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s does not exist.',
                $pluginClass
            ), 0, $e);
        }

        if ($reflClass->isInterface()) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s should be a class, but is an interface.',
                $pluginClass
            ));
        }

        if ($reflClass->isTrait()) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s should be a class, but is a trait.',
                $pluginClass
            ));
        }

        if (!$reflClass->implementsInterface('\Puli\RepositoryManager\Plugin\PluginInterface')) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s must implement \Puli\RepositoryManager\Plugin\PluginInterface.',
                $pluginClass
            ));
        }

        $constructor = $reflClass->getConstructor();

        if (null !== $constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidConfigException(sprintf(
                'The constructor of the plugin class %s must not have required '.
                'parameters.',
                $pluginClass
            ));
        }

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
}
