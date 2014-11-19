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

use Puli\PackageManager\InvalidConfigException;

/**
 * The configuration of the root Puli package.
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
     * @var string
     */
    private $packageRepositoryConfig = '.puli/packages.json';

    /**
     * @var string
     */
    private $generatedResourceRepository = '.puli/resource-repository.php';

    /**
     * @var string
     */
    private $resourceRepositoryCache = '.puli/cache';

    /**
     * @var string[]
     */
    private $pluginClasses = array();

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
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @return string The path to the configuration file.
     */
    public function getPackageRepositoryConfig()
    {
        return $this->packageRepositoryConfig;
    }

    /**
     * Sets the path to the package repository configuration file.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @param string $configPath The path to the configuration file.
     *
     * @throws InvalidConfigException If the path is empty or not a string.
     */
    public function setPackageRepositoryConfig($configPath)
    {
        if (!is_string($configPath)) {
            throw new InvalidConfigException(sprintf(
                'The path to the repository configuration should be a string. '.
                'Got: %s',
                is_object($configPath) ? get_class($configPath) : gettype($configPath)
            ));
        }

        if ('' === $configPath) {
            throw new InvalidConfigException('The path to the repository configuration should not be empty.');
        }

        $this->packageRepositoryConfig = $configPath;
    }

    /**
     * Returns the path where generated resource repository is placed.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @return string The path to the generated resource repository.
     */
    public function getGeneratedResourceRepository()
    {
        return $this->generatedResourceRepository;
    }

    /**
     * Sets the path where generated resource repository is placed.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @param string $repoPath The path to the generated resource repository.
     *
     * @throws InvalidConfigException If the path is empty or not a string.
     */
    public function setGeneratedResourceRepository($repoPath)
    {
        if (!is_string($repoPath)) {
            throw new InvalidConfigException(sprintf(
                'The path to the generated resource repository should be a '.
                'string. Got: %s',
                is_object($repoPath) ? get_class($repoPath) : gettype($repoPath)
            ));
        }

        if ('' === $repoPath) {
            throw new InvalidConfigException('The path to the generated resource repository should not be empty.');
        }

        $this->generatedResourceRepository = $repoPath;
    }

    /**
     * Returns the path where the generated resource repository caches its files.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @return string The path to the resource repository cache.
     */
    public function getResourceRepositoryCache()
    {
        return $this->resourceRepositoryCache;
    }

    /**
     * Sets the path where the generated resource repository caches its files.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @param string $cachePath The path to the resource repository cache.
     *
     * @throws InvalidConfigException If the path is empty or not a string.
     */
    public function setResourceRepositoryCache($cachePath)
    {
        if (!is_string($cachePath)) {
            throw new InvalidConfigException(sprintf(
                'The path to the resource repository cache should be a string. '.
                'Got: %s',
                is_object($cachePath) ? get_class($cachePath) : gettype($cachePath)
            ));
        }

        if ('' === $cachePath) {
            throw new InvalidConfigException('The path to the resource repository cache should not be empty.');
        }

        $this->resourceRepositoryCache = $cachePath;
    }

    /**
     * Returns the plugin classes.
     *
     * @return string[] The fully qualified plugin class names.
     *
     * @see addPluginClass
     */
    public function getPluginClasses()
    {
        return $this->pluginClasses;
    }

    /**
     * Adds a plugin class.
     *
     * The plugin class must be a fully-qualified class name that implements
     * {@link \Puli\PackageManager\Plugin\PluginInterface}. If the class is not
     * found or does not implement that interface, an exception is thrown.
     *
     * The plugin class must not have required parameters in its constructor
     * so that the package manager can successfully instantiate it. If the
     * constructor has required parameters, an exception is thrown.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If the class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\PackageManager\Plugin\PluginInterface}
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

        if (!$reflClass->implementsInterface('\Puli\PackageManager\Plugin\PluginInterface')) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s must implement \Puli\PackageManager\Plugin\PluginInterface.',
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

        $this->pluginClasses[] = $pluginClass;
    }
}
