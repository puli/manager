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

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\NoSuchConfigKeyException;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\IOException;

/**
 * Manages changes to the root package file.
 *
 * Use this class to make persistent changes to the puli.json of a project.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var RootPackageFile
     */
    private $packageFile;

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
        $this->packageFile = $environment->getRootPackageFile();
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
        return $this->packageFile;
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
        $this->packageFile->getConfig()->set($key, $value);

        $this->packageFileStorage->saveRootPackageFile($this->packageFile);
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
        $this->packageFile->getConfig()->merge($values);

        $this->packageFileStorage->saveRootPackageFile($this->packageFile);
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
        $this->packageFile->getConfig()->remove($key);

        $this->packageFileStorage->saveRootPackageFile($this->packageFile);
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
            $this->packageFile->getConfig()->remove($key);
        }

        $this->packageFileStorage->saveRootPackageFile($this->packageFile);
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
        return $this->packageFile->getConfig()->getRaw($key, $default, $fallback);
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
        $values = $this->packageFile->getConfig()->toFlatRawArray($includeFallback);

        // Reorder the returned values
        $keysInDefaultOrder = Config::getKeys();
        $defaultValues = array_fill_keys($keysInDefaultOrder, null);

        if (!$includeUnset) {
            $defaultValues = array_intersect_key($defaultValues, $values);
        }

        return array_replace($defaultValues, $values);
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
        if ($this->packageFile->hasPluginClass($pluginClass)) {
            // Already installed locally
            return;
        }

        $this->packageFile->addPluginClass($pluginClass);

        $this->packageFileStorage->saveRootPackageFile($this->packageFile);
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
        return $this->packageFile->hasPluginClass($pluginClass, $includeGlobal);
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
        return $this->packageFile->getPluginClasses($includeGlobal);
    }
}
