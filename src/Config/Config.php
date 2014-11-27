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
 * A configuration may inherit default values from another configuration:
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
 * $config->get(Config::PULI_DIR, false);
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
 * $config->set(Config::INSTALL_FILE, '{$puli-dir}/install-file.json');
 *
 * echo $config->get(Config::PULI_DIR);
 * // => .puli/install-file.json
 * ```
 *
 * This class was inspired by Composer's Config class.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Config
{
    const PULI_DIR = 'puli-dir';

    const INSTALL_FILE = 'install-file';

    const REPO_DUMP_DIR = 'repo-dump-dir';

    const REPO_DUMP_FILE = 'repo-dump-file';

    const REPO_FILE = 'repo-file';

    /**
     * The accepted config keys.
     *
     * @var string[]
     */
    private static $keys = array(
        self::PULI_DIR => true,
        self::INSTALL_FILE => true,
        self::REPO_DUMP_DIR => true,
        self::REPO_DUMP_FILE => true,
        self::REPO_FILE => true,
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
     * @param string $key      The configuration key.
     * @param bool   $fallback Whether to fall back to the default value if the
     *                         key was not set.
     *
     * @return mixed The value of the configuration key.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function get($key, $fallback = true)
    {
        return $this->replacePlaceholders($this->getRaw($key, $fallback), $fallback);
    }

    /**
     * Returns the raw value of a configuration key.
     *
     * Contrary to {@link get()}, this method does not resolve placeholders:
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
     * @param bool   $fallback Whether to fall back to the default value if the
     *                         key was not set.
     *
     * @return mixed The value of the configuration key.
     *
     * @throws NoSuchConfigKeyException If the configuration key is invalid.
     */
    public function getRaw($key, $fallback = true)
    {
        if (!isset(self::$keys[$key])) {
            throw new NoSuchConfigKeyException(sprintf(
                'The config key "%s" does not exist.',
                $key
            ));
        }

        if (!array_key_exists($key, $this->values) && $fallback && $this->baseConfig) {
            return $this->baseConfig->getRaw($key);
        }

        return isset($this->values[$key]) ? $this->values[$key] : null;
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
        if (!isset(self::$keys[$key])) {
            throw new NoSuchConfigKeyException(sprintf(
                'The config key "%s" does not exist.',
                $key
            ));
        }

        unset($this->values[$key]);
    }

    /**
     * Returns all configuration values.
     *
     * @param bool $includeFallback Whether to include values set in the base
     *                              configuration passed to {@link __construct()}.
     *
     * @return array The configuration values.
     */
    public function toArray($includeFallback = true)
    {
        $values = $this->toRawArray($includeFallback);

        foreach ($values as $key => $value) {
            $values[$key] = $this->replacePlaceholders($value, $includeFallback);
        }

        return $values;
    }

    /**
     * Returns all raw configuration values.
     *
     * Contrary to {@link toArray()}, this method does not resolve placeholders:
     *
     * ```php
     * $config = new Config();
     * $config->set(Config::PULI_DIR, '.puli');
     * $config->set(Config::INSTALL_FILE, '{$puli-dir}/install-file.json');
     *
     * print_r($config->toArray());
     * // Array(
     * //   'puli-dir' => '.puli',
     * //   'install-file' => '.puli/install-file.json',
     * // )
     *
     * print_r($config->toRawArray());
     * // Array(
     * //   'puli-dir' => '.puli',
     * //   'install-file' => '{$puli-dir}/install-file.json',
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
            ? array_replace($this->baseConfig->values, $this->values)
            : $this->values;
    }

    private function validate($key, $value)
    {
        switch ($key) {
            case self::PULI_DIR:
            case self::INSTALL_FILE:
            case self::REPO_DUMP_DIR:
            case self::REPO_DUMP_FILE:
            case self::REPO_FILE:
                $this->validateNotNull($key, $value);
                $this->validateNonEmptyString($key, $value);
                break;
        }
    }

    private function validateNotNull($key, $value)
    {
        if (null === $value) {
            throw new InvalidConfigException(sprintf(
                'The config key "%s" must not be null. Use remove() to unset '.
                'keys.',
                $key
            ));
        }
    }

    private function validateNonEmptyString($key, $value)
    {
        if (!is_string($value) && null !== $value) {
            throw new InvalidConfigException(sprintf(
                'The config key "%s" expects a string. Got: %s',
                $key,
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }

        if ('' === $value) {
            throw new InvalidConfigException(sprintf(
                'The value of the config key "%s" must not be empty.',
                $key
            ));
        }
    }

    private function replacePlaceholders($raw, $fallback = true)
    {
        if (!is_string($raw)) {
            return $raw;
        }

        $config = $this;

        return preg_replace_callback('~\{\$(.+)\}~', function ($matches) use ($config, $fallback) {
            return $config->get($matches[1], $fallback);
        }, $raw);
    }

}
