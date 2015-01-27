<?php

/*
 * This file is part of the puli/repository.itory-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Config;

use Puli\RepositoryManager\Api\InvalidConfigException;

/**
 * Stores configuration values.
 *
 * Use the methods {@link get()}, {@link set()} and {@link merge()} to retrieve
 * and store values:
 *
 * ```php
 * $config = new Config();
 * $config->set(Config::PULI_DIR, '.puli');
 *
 * echo $config->get(Config::PULI_DIR);
 * // => .puli
 * ```
 *
 * You can customize the value returned by {@link get()} if a key is not set
 * by passing that value in the second parameter:
 *
 * ```php
 * $config = new Config();
 *
 * echo $config->get(Config::PULI_DIR, '.puli');
 * ```
 *
 * A configuration may also inherit default values from another configuration:
 *
 * ```php
 * $defaultConfig = new Config();
 * $defaultConfig->set(Config::PULI_DIR, '.puli');
 *
 * $config = new Config($defaultConfig);
 *
 * $config->get(Config::PULI_DIR);
 * // => .puli
 * ```
 *
 * You can disable the fallback to the default value by passing `false` to
 * {@link get()}:
 *
 * ```php
 * $defaultConfig = new Config();
 * $defaultConfig->set(Config::PULI_DIR, '.puli');
 *
 * $config = new Config($defaultConfig);
 *
 * $config->get(Config::PULI_DIR, null, false);
 * // => null
 * ```
 *
 * Configuration values support placeholders for other values in the format
 * `{$<key>}`. These placeholders will be replaced by the actual values of the
 * referenced keys when the values are accessed:
 *
 * ```php
 * $config = new Config();
 * $config->set(Config::PULI_DIR, '.puli');
 * $config->set(Config::FACTORY_FILE, '{$puli-dir}/PuliFactory.php');
 *
 * echo $config->get(Config::FACTORY_FILE);
 * // => .puli/PuliRegistry.php
 * ```
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Config
{
    const PULI_DIR = 'puli-dir';

    const FACTORY = 'factory';

    const FACTORY_AUTO_GENERATE = 'factory.auto-generate';

    const FACTORY_CLASS = 'factory.class';

    const FACTORY_FILE = 'factory.file';

    const REPOSITORY = 'repository';

    const REPOSITORY_TYPE = 'repository.type';

    const REPOSITORY_PATH = 'repository.path';

    const REPOSITORY_SYMLINK = 'repository.symlink';

    const DISCOVERY = 'discovery';

    const DISCOVERY_TYPE = 'discovery.type';

    const DISCOVERY_STORE = 'discovery.store';

    const DISCOVERY_STORE_TYPE = 'discovery.store.type';

    const DISCOVERY_STORE_PATH = 'discovery.store.path';

    const DISCOVERY_STORE_SERVER = 'discovery.store.server';

    const DISCOVERY_STORE_PORT = 'discovery.store.port';

    const DISCOVERY_STORE_CACHE = 'discovery.store.cache';

    /**
     * The accepted config keys.
     *
     * @var bool[]
     */
    private static $keys = array(
        self::PULI_DIR => true,
        self::FACTORY_AUTO_GENERATE => true,
        self::FACTORY_CLASS => true,
        self::FACTORY_FILE => true,
        self::REPOSITORY_TYPE => true,
        self::REPOSITORY_PATH => true,
        self::REPOSITORY_SYMLINK => true,
        self::DISCOVERY_TYPE => true,
        self::DISCOVERY_STORE_TYPE => true,
        self::DISCOVERY_STORE_PATH => true,
        self::DISCOVERY_STORE_SERVER => true,
        self::DISCOVERY_STORE_PORT => true,
        self::DISCOVERY_STORE_CACHE => true,
    );

    private static $compositeKeys = array(
        self::FACTORY => true,
        self::REPOSITORY => true,
        self::DISCOVERY => true,
        self::DISCOVERY_STORE => true,
    );

    /**
     * The configuration values.
     *
     * @var array
     */
    private $values = array();

    /**
     * The configuration to fall back to.
     *
     * @var Config
     */
    private $baseConfig;

    /**
     * Returns all valid configuration keys.
     *
     * @return string[] The config keys.
     */
    public static function getKeys()
    {
        return array_keys(self::$keys);
    }

    /**
     * Creates a new configuration.
     *
     * @param Config $baseConfig The configuration to fall back to if a value is
     *                           not set in here.
     * @param array  $values     The values to initially set in the configuration.
     */
    public function __construct(Config $baseConfig = null, array $values = array())
    {
        $this->baseConfig = $baseConfig;

        $this->merge($values);
    }

    /**
     * Returns the base configuration.
     *
     * @return Config The base configuration or `null` if none is set.
     */
    public function getBaseConfig()
    {
        return $this->baseConfig;
    }

    /**
     * Returns the value of a configuration key.
     *
     * If fallback is enabled, the value of the base configuration is returned
     * if the key was not set.
     *
     * You can also pass a default value in the second parameter. This default
     * value is returned if the configuration key was neither found in this nor
     * in its fallback configuration.
     *
     * @param string $key      The configuration key.
     * @param mixed  $default  The value to return if the key was not set.
     * @param bool   $fallback Whether to return the value of the base
     *                         configuration if the key was not set.
     *
     * @return mixed The value of the configuration key.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function get($key, $default = null, $fallback = true)
    {
        return $this->replacePlaceholders($this->getRaw($key, $default, $fallback), $fallback);
    }

    /**
     * Returns the raw value of a configuration key.
     *
     * Unlike {@link get()}, this method does not resolve placeholders:
     *
     * ```php
     * $config = new Config();
     * $config->set(Config::PULI_DIR, '.puli');
     * $config->set(Config::INSTALL_FILE, '{$puli-dir}/install-file.json');
     *
     * echo $config->get(Config::PULI_DIR);
     * // => .puli/install-file.json
     *
     * echo $config->getRaw(Config::PULI_DIR);
     * // => {$puli-dir}/install-file.json
     * ```
     *
     * @param string $key      The configuration key.
     * @param mixed  $default  The value to return if the key was not set.
     * @param bool   $fallback Whether to return the value of the base
     *                         configuration if the key was not set.
     *
     * @return mixed The value of the configuration key.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function getRaw($key, $default = null, $fallback = true)
    {
        if (isset(self::$compositeKeys[$key])) {
            return array_replace_recursive(
                is_array($default) ? $default : array(),
                $fallback && $this->baseConfig ? $this->baseConfig->getRaw($key) : array(),
                $this->filterByKeyPrefix($key.'.')
            );
        }

        if (!isset(self::$keys[$key])) {
            throw NoSuchConfigKeyException::forKey($key);
        }

        if (!array_key_exists($key, $this->values) && $fallback && $this->baseConfig) {
            return $this->baseConfig->getRaw($key, $default);
        }

        return isset($this->values[$key]) ? $this->values[$key] : $default;
    }

    /**
     * Returns whether a configuration key is set.
     *
     * @param string $key      The configuration key to search.
     * @param bool   $fallback Whether to check the base configuration if the
     *                         key is not found.
     *
     * @return bool Returns `true` if the configuration key is set.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function contains($key, $fallback = true)
    {
        if (!isset(self::$compositeKeys[$key]) && !isset(self::$keys[$key])) {
            throw NoSuchConfigKeyException::forKey($key);
        }

        if (array_key_exists($key, $this->values)) {
            return true;
        }

        if (isset(self::$compositeKeys[$key]) && $this->containsKeyPrefix($key.'.')) {
            return true;
        }

        if ($fallback && $this->baseConfig) {
            return $this->baseConfig->contains($key);
        }

        return false;
    }

    /**
     * Sets the value of a configuration key.
     *
     * @param string $key   The configuration key.
     * @param mixed  $value The value to set.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     * @throws InvalidConfigException If the value is invalid.
     */
    public function set($key, $value)
    {
        if (isset(self::$compositeKeys[$key])) {
            $this->assertArray($key, $value);
            $this->removeByKeyPrefix($key.'.');

            foreach ($value as $k => $v) {
                $this->set($key.'.'.$k, $v);
            }

            return;
        }

        if (!isset(self::$keys[$key])) {
            throw NoSuchConfigKeyException::forKey($key);
        }

        $this->validate($key, $value);

        $this->values[$key] = $value;
    }

    /**
     * Sets a list of configuration values.
     *
     * @param array $values The values to set.
     *
     * @throws NoSuchConfigKeyException If a configuration key is invalid.
     * @throws InvalidConfigException If a value is invalid.
     */
    public function merge(array $values)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Removes a configuration key.
     *
     * If the configuration has a base configuration, the default value will
     * be returned by {@link get()} after removing the key.
     *
     * @param string $key The configuration key to remove.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function remove($key)
    {
        if (isset(self::$compositeKeys[$key])) {
            $this->removeByKeyPrefix($key.'.');

            return;
        }

        if (!isset(self::$keys[$key])) {
            throw NoSuchConfigKeyException::forKey($key);
        }

        unset($this->values[$key]);
    }

    /**
     * Returns all configuration values as flat array.
     *
     * @param bool $includeFallback Whether to include values set in the base
     *                              configuration passed to {@link __construct()}.
     *
     * @return array The configuration values.
     */
    public function toFlatArray($includeFallback = true)
    {
        return $this->replacePlaceholders($this->toFlatRawArray($includeFallback), $includeFallback);
    }

    /**
     * Returns all raw configuration values as flat array.
     *
     * Unlike {@link toFlatArray()}, this method does not resolve placeholders:
     *
     * ```php
     * $config = new Config();
     * $config->set(Config::PULI_DIR, '.puli');
     * $config->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');
     *
     * print_r($config->toFlatArray());
     * // Array(
     * //   'puli-dir' => '.puli',
     * //   'registry-file' => '.puli/ServiceRegistry.php',
     * // )
     *
     * print_r($config->toFlatRawArray());
     * // Array(
     * //   'puli-dir' => '.puli',
     * //   'registry-file' => '{$puli-dir}/ServiceRegistry.php',
     * // )
     * ```
     *
     * @param bool $includeFallback Whether to include values set in the base
     *                              configuration passed to {@link __construct()}.
     *
     * @return array The raw configuration values.
     */
    public function toFlatRawArray($includeFallback = true)
    {
        return $includeFallback && $this->baseConfig
            ? array_replace($this->baseConfig->toFlatRawArray(), $this->values)
            : $this->values;
    }

    /**
     * Returns all configuration values as nested array.
     *
     * @param bool $includeFallback Whether to include values set in the base
     *                              configuration passed to {@link __construct()}.
     *
     * @return array The configuration values.
     */
    public function toArray($includeFallback = true)
    {
        return $this->replacePlaceholders($this->toRawArray($includeFallback), $includeFallback);
    }

    /**
     * Returns all raw configuration values as nested array.
     *
     * Unlike {@link toArray()}, this method does not resolve placeholders:
     *
     * ```php
     * $config = new Config();
     * $config->set(Config::PULI_DIR, '.puli');
     * $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/repository');
     *
     * print_r($config->toArray());
     * // Array(
     * //     'puli-dir' => '.puli',
     * //     'repository. => array(
     * //         'storage-dir' => '.puli/repository',
     * //      ),
     * // )
     *
     * print_r($config->toRawArray());
     * // Array(
     * //     'puli-dir' => '.puli',
     * //     'repository. => array(
     * //         'storage-dir' => '{$puli-dir}/repository',
     * //      ),
     * // )
     * ```
     *
     * @param bool $includeFallback Whether to include values set in the base
     *                              configuration passed to {@link __construct()}.
     *
     * @return array The raw configuration values.
     */
    public function toRawArray($includeFallback = true)
    {
        $values = array();

        foreach ($this->values as $key => $value) {
            $this->addKeyValue($key, $value, $values);
        }

        return $includeFallback && $this->baseConfig
            ? array_replace_recursive($this->baseConfig->toRawArray(), $values)
            : $values;
    }

    private function validate($key, $value)
    {
        switch ($key) {
            case self::FACTORY_AUTO_GENERATE:
            case self::REPOSITORY_SYMLINK:
            case self::DISCOVERY_STORE_CACHE:
                $this->assertNotNull($key, $value);
                $this->assertBoolean($key, $value);
                break;

            case self::DISCOVERY_STORE_PORT:
                $this->assertNotNull($key, $value);
                $this->assertInteger($key, $value);
                break;

            case self::DISCOVERY_STORE_TYPE:
                if (null !== $value) {
                    $this->assertString($key, $value);
                    $this->assertNonEmpty($key, $value);
                }
                break;

            default:
                $this->assertNotNull($key, $value);
                $this->assertString($key, $value);
                $this->assertNonEmpty($key, $value);
                break;
        }
    }

    private function assertArray($key, $value)
    {
        if (!is_array($value)) {
            throw new InvalidConfigException(sprintf(
                'The config key "%s" must be an array. Got: %s',
                $key,
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }

    private function assertNotNull($key, $value)
    {
        if (null === $value) {
            throw new InvalidConfigException(sprintf(
                'The config key "%s" must not be null. Use remove() to unset '.
                'keys.',
                $key
            ));
        }
    }

    private function assertString($key, $value)
    {
        if (!is_string($value) && null !== $value) {
            throw new InvalidConfigException(sprintf(
                'The config key "%s" must be a string. Got: %s',
                $key,
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }

    private function assertInteger($key, $value)
    {
        if (!is_int($value) && null !== $value) {
            throw new InvalidConfigException(sprintf(
                'The config key "%s" must be an integer. Got: %s',
                $key,
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }

    private function assertBoolean($key, $value)
    {
        if (!is_bool($value) && null !== $value) {
            throw new InvalidConfigException(sprintf(
                'The config key "%s" must be a bool. Got: %s',
                $key,
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }

    private function assertNonEmpty($key, $value)
    {
        if ('' === $value) {
            throw new InvalidConfigException(sprintf(
                'The value of the config key "%s" must not be empty.',
                $key
            ));
        }
    }

    private function replacePlaceholders($raw, $fallback = true)
    {
        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                $raw[$key] = $this->replacePlaceholders($value, $fallback);
            }

            return $raw;
        }

        if (!is_string($raw)) {
            return $raw;
        }

        $config = $this;

        return preg_replace_callback('~\{\$(.+)\}~', function ($matches) use ($config, $fallback) {
            return $config->get($matches[1], null, $fallback);
        }, $raw);
    }

    private function filterByKeyPrefix($keyPrefix)
    {
        $values = array();
        $offset = strlen($keyPrefix);

        foreach ($this->values as $k => $v) {
            if (0 !== strpos($k, $keyPrefix)) {
                continue;
            }

            $this->addKeyValue(substr($k, $offset), $v, $values);
        }

        return $values;
    }

    private function containsKeyPrefix($keyPrefix)
    {
        foreach ($this->values as $k => $v) {
            if (0 === strpos($k, $keyPrefix)) {
                return true;
            }
        }

        return false;
    }

    private function removeByKeyPrefix($keyPrefix)
    {
        foreach ($this->values as $k => $v) {
            if (0 !== strpos($k, $keyPrefix)) {
                continue;
            }

            unset($this->values[$k]);
        }
    }

    private function addKeyValue($key, $value, &$values)
    {
        $target = &$values;
        $keyParts = explode('.', $key);

        for ($i = 0, $l = count($keyParts) - 1; $i < $l; ++$i) {
            if (!isset($target[$keyParts[$i]])) {
                $target[$keyParts[$i]] = array();
            }

            $target = &$target[$keyParts[$i]];
        }

        $target[$keyParts[$l]] = $value;
    }

}
