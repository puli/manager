<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Config;

use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\IOException;
use Webmozart\Expression\Expression;

/**
 * Manages changes to the configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConfigManager
{
    /**
     * Returns the managed configuration.
     *
     * @return Config The managed configuration.
     */
    public function getConfig();

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
     * Removes the config keys from the file that match the given expression.
     *
     * The file is saved directly after removing the keys.
     *
     * @param Expression $expr The search criteria.
     *
     * @throws NoSuchConfigKeyException If a configuration key is invalid.
     * @throws IOException If the file cannot be written.
     */
    public function removeConfigKeys(Expression $expr);

    /**
     * Removes all config keys from the file.
     *
     * The file is saved directly after removing the keys.
     *
     * @throws IOException If the file cannot be written.
     */
    public function clearConfigKeys();

    /**
     * Returns whether a configuration key exists.
     *
     * @param string $key             The configuration key to search.
     * @param bool   $includeFallback Whether to check the base configuration if
     *                                the key is not found.
     *
     * @return bool Returns `true` if the file contains the key.
     */
    public function hasConfigKey($key, $includeFallback = false);

    /**
     * Returns whether the file contains any configuration keys.
     *
     * You can optionally pass an expression to check whether the file contains
     * configuration keys matching the expression.
     *
     * @param Expression $expr            The search criteria.
     * @param bool       $includeFallback Whether to check the base configuration
     *                                    if the key is not found.
     *
     * @return bool Returns `true` if the file contains configuration keys and
     *              `false` otherwise. If an expression is passed, this method
     *              only returns `true` if the file contains keys matching the
     *              expression.
     */
    public function hasConfigKeys(Expression $expr = null, $includeFallback = false);

    /**
     * Returns the value of a configuration key.
     *
     * The value is returned raw as it is written in the file.
     *
     * @param string $key      The configuration key.
     * @param mixed  $default  The value to return if the key was not set.
     * @param bool   $fallback Whether to return the value of the base
     *                         configuration if the key was not set.
     * @param bool   $raw      Whether to return the raw value of the key. If
     *                         `true`, placeholders in the value are replaced
     *                         by their actual values.
     *
     * @return mixed The value of the key or the default value, if none is set.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function getConfigKey($key, $default = null, $fallback = false, $raw = true);

    /**
     * Returns the values of all configuration keys set in the file.
     *
     * The values are returned raw as they are written in the file.
     *
     * @param bool $includeFallback Whether to include values set in the base
     *                              configuration.
     * @param bool $includeUnset    Whether to include unset keys in the result.
     * @param bool $raw             Whether to return the raw values of the keys.
     *                              If `true`, placeholders in the values are
     *                              replaced by their actual values.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function getConfigKeys($includeFallback = false, $includeUnset = false, $raw = true);

    /**
     * Returns the values of all configuration keys matching the given
     * expression.
     *
     * @param Expression $expr            The search criteria.
     * @param bool       $includeFallback Whether to include values set in the
     *                                    base configuration.
     * @param bool       $includeUnset    Whether to include unset keys in the
     *                                    result.
     * @param bool       $raw             Whether to return the raw values of
     *                                    the keys. If `true`, placeholders in
     *                                    the values are replaced by their
     *                                    actual values.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function findConfigKeys(Expression $expr, $includeFallback = false, $includeUnset = false, $raw = true);
}
