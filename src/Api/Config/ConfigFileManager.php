<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Config;

use Puli\RepositoryManager\Api\Environment\GlobalEnvironment;
use Puli\RepositoryManager\Api\InvalidConfigException;
use Puli\RepositoryManager\Api\IOException;

/**
 * Manages changes to the global configuration file.
 *
 * Use this class to make persistent changes to the global config.json.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConfigFileManager
{
    /**
     * Returns the global environment.
     *
     * @return GlobalEnvironment The global environment.
     */
    public function getEnvironment();

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
    public function setConfigKey($key, $value);

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
    public function setConfigKeys(array $values);

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
    public function removeConfigKey($key);

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
    public function removeConfigKeys(array $keys);

    /**
     * Returns whether a configuration key exists.
     *
     * @param string $key      The configuration key to search.
     * @param bool   $fallback Whether to check the base configuration if the
     *                         key is not found.
     *
     * @return bool Returns `true` if the file contains the key.
     */
    public function hasConfigKey($key, $fallback = false);

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
    public function getConfigKey($key, $default = null, $fallback = false);

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
    public function getConfigKeys($includeFallback = false, $includeUnset = false);

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
    public function findConfigKeys($pattern, $includeFallback = false, $includeUnset = false);
}
