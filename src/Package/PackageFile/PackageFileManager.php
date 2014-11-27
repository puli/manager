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
     * @param string $key The configuration key.
     *
     * @return mixed The value of the key or `null`, if none is set.
     *
     * @throws NoSuchConfigKeyException If a configuration key is invalid.
     */
    public function getConfigKey($key)
    {
        // We're only interested in the value of the file, not in the default
        return $this->rootPackageFile->getConfig()->getRaw($key, false);
    }

    /**
     * Returns the value of all configuration keys set in the file.
     *
     * The values are returned raw as they are written in the file.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function getConfigKeys()
    {
        // We're only interested in the values of the file, not in the defaults
        return $this->rootPackageFile->getConfig()->toRawArray(false);
    }

    /**
     * Installs a plugin class.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link \Puli\RepositoryManager\Plugin\PluginInterface}. Plugin
     * constructors must not have mandatory parameters.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @throws InvalidConfigException If a class is not found, is not a class,
     *                                does not implement
     *                                {@link \Puli\RepositoryManager\Plugin\PluginInterface}
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
