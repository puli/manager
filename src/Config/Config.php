<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config;

use Puli\RepositoryManager\InvalidConfigException;

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
 * $config->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');
 *
 * echo $config->get(Config::REGISTRY_FILE);
 * // => .puli/ServiceRegistry.php
 * ```
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Config
{
    const PULI_DIR = 'puli-dir';

    const REGISTRY_CLASS = 'registry-class';

    const REGISTRY_FILE = 'registry-file';

    const REPO = 'repo';

    const REPO_TYPE = 'repo.type';

    const REPO_STORAGE_DIR = 'repo.storage-dir';

    const REPO_VERSION_STORE = 'repo.version-store';

    const REPO_VERSION_STORE_TYPE = 'repo.version-store.type';

    const REPO_VERSION_STORE_PATH = 'repo.version-store.path';

    const DISCOVERY = 'discovery';

    const DISCOVERY_TYPE = 'discovery.type';

    const DISCOVERY_STORE = 'discovery.store';

    const DISCOVERY_STORE_TYPE = 'discovery.store.type';

    const DISCOVERY_STORE_PATH = 'discovery.store.path';

    /**
     * The accepted config keys.
     *
     * @var bool[]
     */
    private static $keys = array(
        self::PULI_DIR => true,
        self::REGISTRY_CLASS => true,
        self::REGISTRY_FILE => true,
        self::REPO_TYPE => true,
        self::REPO_STORAGE_DIR => true,
        self::REPO_VERSION_STORE_TYPE => true,
        self::REPO_VERSION_STORE_PATH => true,
        self::DISCOVERY_TYPE => true,
        self::DISCOVERY_STORE_TYPE => true,
        self::DISCOVERY_STORE_PATH => true,
    );

    private static $compositeKeys = array(
        self::REPO => true,
        self::REPO_VERSION_STORE => true,
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
     */
    public function getRaw($key, $default = null, $fallback = true)
    {
        if (isset(self::$compositeKeys[$key])) {
            return array_replace(
                is_array($default) ? $default : array(),
                $fallback && $this->baseConfig ? $this->baseConfig->filterByKeyPrefix($key.'.') : array(),
                $this->filterByKeyPrefix($key.'.')
            );
        }

        if (!isset(self::$keys[$key])) {
            throw new NoSuchConfigKeyException(sprintf(
                'The config key "%s" does not exist.',
                $key
            ));
        }

        if (!array_key_exists($key, $this->values) && $fallback && $this->baseConfig) {
            return $this->baseConfig->getRaw($key, $default);
        }

        return isset($this->values[$key]) ? $this->values[$key] : $default;
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
            throw new NoSuchConfigKeyException(sprintf(
                'The config key "%s" does not exist.',
                $key
            ));
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
            throw new NoSuchConfigKeyException(sprintf(
                'The config key "%s" does not exist.',
                $key
            ));
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
    public function toArray($includeFallback = true)
    {
        return $this->replacePlaceholders($this->toRawArray($includeFallback), $includeFallback);
    }

    /**
     * Returns all raw configuration values as flat array.
     *
     * Unlike {@link toArray()}, this method does not resolve placeholders:
     *
     * ```php
     * $config = new Config();
     * $config->set(Config::PULI_DIR, '.puli');
     * $config->set(Config::REGISTRY_FILE, '{$puli-dir}/ServiceRegistry.php');
     *
     * print_r($config->toArray());
     * // Array(
     * //   'puli-dir' => '.puli',
     * //   'registry-file' => '.puli/ServiceRegistry.php',
     * // )
     *
     * print_r($config->toRawArray());
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
    public function toRawArray($includeFallback = true)
    {
        return $includeFallback && $this->baseConfig
            ? array_replace($this->baseConfig->toRawArray(), $this->values)
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
    public function toNestedArray($includeFallback = true)
    {
        return $this->replacePlaceholders($this->toRawNestedArray($includeFallback), $includeFallback);
    }

    /**
     * Returns all raw configuration values as nested array.
     *
     * Unlike {@link toNestedArray()}, this method does not resolve placeholders:
     *
     * ```php
     * $config = new Config();
     * $config->set(Config::PULI_DIR, '.puli');
     * $config->set(Config::REPO_STORAGE_DIR, '{$puli-dir}/repository');
     *
     * print_r($config->toNestedArray());
     * // Array(
     * //     'puli-dir' => '.puli',
     * //     'repo' => array(
     * //         'storage-dir' => '.puli/repository',
     * //      ),
     * // )
     *
     * print_r($config->toRawNestedArray());
     * // Array(
     * //     'puli-dir' => '.puli',
     * //     'repo' => array(
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
    public function toRawNestedArray($includeFallback = true)
    {
        $values = array();

        foreach ($this->values as $key => $value) {
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

        return $includeFallback && $this->baseConfig
            ? array_replace_recursive($this->baseConfig->toRawNestedArray(), $values)
            : $values;
    }

    private function validate($key, $value)
    {
        switch ($key) {
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

            $values[substr($k, $offset)] = $v;
        }

        return $values;
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

}
