<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config;

use Puli\RepositoryManager\InvalidConfigException;

/**
 * The system-wide Puli configuration.
 *
 * Root packages inherit their settings from this configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalConfig
{
    /**
     * The default location of the install file.
     *
     * This value is used when no custom value is configured.
     */
    const DEFAULT_INSTALL_FILE = '.puli/packages.json';

    /**
     * The default location of the generated resource repository.
     *
     * This value is used when no custom value is configured.
     */
    const DEFAULT_GENERATED_RESOURCE_REPOSITORY = '.puli/resource-repository.php';

    /**
     * The default location of the resource repository cache.
     *
     * This value is used when no custom value is configured.
     */
    const DEFAULT_RESOURCE_REPOSITORY_CACHE = '.puli/cache';

    /**
     * @var string|null
     */
    private $path;

    /**
     * @var string|null
     */
    private $installFile;

    /**
     * @var string|null
     */
    private $generatedResourceRepository;

    /**
     * @var string|null
     */
    private $resourceRepositoryCache;

    /**
     * @var string[]
     */
    private $pluginClasses = array();

    /**
     * Creates a new global configuration.
     *
     * @param string|null $path The path where the configuration is stored or
     *                          `null` if this configuration is not stored on
     *                          the file system.
     *
     * @throws \InvalidArgumentException If the path is not a string or empty.
     */
    public function __construct($path = null)
    {
        if (!is_string($path) && null !== $path) {
            throw new \InvalidArgumentException(sprintf(
                'The path to the global configuration should be a string '.
                'or null. Got: %s',
                is_object($path) ? get_class($path) : gettype($path)
            ));
        }

        if ('' === $path) {
            throw new \InvalidArgumentException('The path to the global configuration should not be empty.');
        }

        $this->path = $path;
    }

    /**
     * Returns the file system path of the configuration file.
     *
     * @return string|null The path or `null` if this configuration is not
     *                     stored on the file system.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the path to the install file.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * If no path is set and fallback is enabled, a default path is returned.
     *
     * @param bool $fallback Whether to fall back to a predefined default value
     *                       if no value was set. Defaults to `true`.
     *
     * @return string|null The path to the install file of `null` if none is set.
     */
    public function getInstallFile($fallback = true)
    {
        return null === $this->installFile && $fallback
            ? self::DEFAULT_INSTALL_FILE
            : $this->installFile;
    }

    /**
     * Sets the path to the install file.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @param string|null $path The path to the install file or `null` to unset.
     *
     * @throws InvalidConfigException If the path is empty or not a string/`null`.
     */
    public function setInstallFile($path)
    {
        if (!is_string($path) && null !== $path) {
            throw new InvalidConfigException(sprintf(
                'The path to the install file should be a string or null. Got: %s',
                is_object($path) ? get_class($path) : gettype($path)
            ));
        }

        if ('' === $path) {
            throw new InvalidConfigException('The path to the install file should not be empty.');
        }

        $this->installFile = $path;
    }

    /**
     * Returns the path where generated resource repository is placed.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * If no path is set and fallback is enabled, a default path is returned.
     *
     * @param bool $fallback Whether to fall back to a predefined default value
     *                       if no value was set. Defaults to `true`.
     *
     * @return string|null The path to the generated resource repository or
     *                     `null` if none is set.
     */
    public function getGeneratedResourceRepository($fallback = true)
    {
        return null === $this->generatedResourceRepository && $fallback
            ? self::DEFAULT_GENERATED_RESOURCE_REPOSITORY
            : $this->generatedResourceRepository;
    }

    /**
     * Sets the path where generated resource repository is placed.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @param string|null $repoPath The path to the generated resource
     *                              repository or `null` to unset.
     *
     * @throws InvalidConfigException If the path is empty or not a string/`null`.
     */
    public function setGeneratedResourceRepository($repoPath)
    {
        if (!is_string($repoPath) && null !== $repoPath) {
            throw new InvalidConfigException(sprintf(
                'The path to the generated resource repository should be a '.
                'string or null. Got: %s',
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
     * If no path is set and fallback is enabled, a default path is returned.
     *
     * @param bool $fallback Whether to fall back to a predefined default value
     *                       if no value was set. Defaults to `true`.
     *
     * @return string|null The path to the resource repository cache or `null`
     *                     if none is set.
     */
    public function getResourceRepositoryCache($fallback = true)
    {
        return null === $this->resourceRepositoryCache && $fallback
            ? self::DEFAULT_RESOURCE_REPOSITORY_CACHE
            : $this->resourceRepositoryCache;
    }

    /**
     * Sets the path where the generated resource repository caches its files.
     *
     * If the path is relative, it is calculated relative to the install path
     * of the root package.
     *
     * @param string|null $cachePath The path to the resource repository cache
     *                               or `null` to unset.
     *
     * @throws InvalidConfigException If the path is empty or not a string/`null`.
     */
    public function setResourceRepositoryCache($cachePath)
    {
        if (!is_string($cachePath) && null !== $cachePath) {
            throw new InvalidConfigException(sprintf(
                'The path to the resource repository cache should be a string '.
                'or null. Got: %s',
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
