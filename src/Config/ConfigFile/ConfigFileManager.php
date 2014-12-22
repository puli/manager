<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config\ConfigFile;

use Puli\RepositoryManager\Config\NoSuchConfigKeyException;
use Puli\RepositoryManager\Environment\GlobalEnvironment;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\IOException;

/**
 * Manages changes to the global configuration file.
 *
 * Use this class to make persistent changes to the global config.json.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileManager
{
    /**
     * @var GlobalEnvironment
     */
    private $environment;

    /**
     * @var ConfigFile
     */
    private $configFile;

    /**
     * @var ConfigFileStorage
     */
    private $configFileStorage;

    /**
     * Creates the configuration manager.
     *
     * @param GlobalEnvironment $environment       The global environment.
     * @param ConfigFileStorage $configFileStorage The configuration file storage.
     */
    public function __construct(GlobalEnvironment $environment, ConfigFileStorage $configFileStorage)
    {
        $this->environment = $environment;
        $this->configFileStorage = $configFileStorage;
        $this->configFile = $environment->getConfigFile();
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
        $this->configFile->getConfig()->set($key, $value);

        $this->configFileStorage->saveConfigFile($this->configFile);
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
        $this->configFile->getConfig()->merge($values);

        $this->configFileStorage->saveConfigFile($this->configFile);
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
        $this->configFile->getConfig()->remove($key);

        $this->configFileStorage->saveConfigFile($this->configFile);
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
            $this->configFile->getConfig()->remove($key);
        }

        $this->configFileStorage->saveConfigFile($this->configFile);
    }

    /**
     * Returns the value of a configuration key.
     *
     * The value is returned raw as it is written in the file.
     *
     * @param string $key     The configuration key.
     * @param mixed  $default The value to return if the key was not set.
     *
     * @return mixed The value of the key or the default value, if none is set.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function getConfigKey($key, $default = null)
    {
        // We're only interested in the value of the file, not in the default
        return $this->configFile->getConfig()->getRaw($key, $default, false);
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
        return $this->configFile->getConfig()->toRawArray(false);
    }
}
