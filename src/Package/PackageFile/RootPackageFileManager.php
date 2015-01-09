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
class RootPackageFileManager
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
     * Sets a config key in the file.
     *
     * The file is saved directly after setting the key.
     *
     * @param string $key   The configuration key.
     * @param mixed  $value The new value.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     * @throws InvalidConfigException If the value is invalid.
     * @throws IOException If the file cannot be written.
     */
    public function setConfigKey($key, $value)
    {
        $this->rootPackageFile->getConfig()->set($key, $value);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Sets config keys in the file.
     *
     * The file is saved directly after setting the keys.
     *
     * @param string[] $values The map from configuration keys to values.
     *
     * @throws NoSuchConfigKeyException If a configuration key is invalid.
     * @throws InvalidConfigException If a value is invalid.
     * @throws IOException If the file cannot be written.
     */
    public function setConfigKeys(array $values)
    {
        $this->rootPackageFile->getConfig()->merge($values);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Removes a config key from the file.
     *
     * The file is saved directly after removing the key.
     *
     * @param string $key The removed configuration key.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     * @throws IOException If the file cannot be written.
     */
    public function removeConfigKey($key)
    {
        $this->rootPackageFile->getConfig()->remove($key);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Removes config keys from the file.
     *
     * The file is saved directly after removing the keys.
     *
     * @param string[] $keys The removed configuration keys.
     *
     * @throws NoSuchConfigKeyException If a configuration key is invalid.
     * @throws IOException If the file cannot be written.
     */
    public function removeConfigKeys(array $keys)
    {
        foreach ($keys as $key) {
            $this->rootPackageFile->getConfig()->remove($key);
        }

        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Returns the value of a configuration key.
     *
     * The value is returned raw as it is written in the file.
     *
     * @param string $key      The configuration key.
     * @param mixed  $default  The value to return if the key was not set.
     * @param bool   $fallback Whether to return the value of the base
     *                         configuration if the key was not set.
     *
     * @return mixed The value of the key or the default value, if none is set.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function getConfigKey($key, $default = null, $fallback = false)
    {
        return $this->rootPackageFile->getConfig()->getRaw($key, $default, $fallback);
    }

    /**
     * Returns the value of all configuration keys set in the file.
     *
     * The values are returned raw as they are written in the file.
     *
     * @param bool $includeFallback Whether to include values set in the base
     *                              configuration.
     * @param bool $includeUnset    Whether to include unset keys in the result.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function getConfigKeys($includeFallback = false, $includeUnset = false)
    {
        $values = $this->rootPackageFile->getConfig()->toFlatRawArray($includeFallback);

        // Reorder the returned values
        $keysInDefaultOrder = Config::getKeys();
        $defaultValues = array_fill_keys($keysInDefaultOrder, null);

        if (!$includeUnset) {
            $defaultValues = array_intersect_key($defaultValues, $values);
        }

        return array_replace($defaultValues, $values);
    }

    /**
     * Returns the values of all configuration keys matching a pattern.
     *
     * @param string $pattern         The configuration key pattern. May contain
     *                                the wildcard "*".
     * @param bool   $includeFallback Whether to include values set in the base
     *                                configuration.
     * @param bool   $includeUnset    Whether to include unset keys in the result.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function findConfigKeys($pattern, $includeFallback = false, $includeUnset = false)
    {
        $values = $this->getConfigKeys($includeFallback, $includeUnset);

        $iterator = new GlobFilterIterator(
            $pattern,
            new ArrayIterator($values),
            GlobFilterIterator::FILTER_KEY | GlobFilterIterator::KEY_AS_KEY
        );

        return iterator_to_array($iterator);
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
}
