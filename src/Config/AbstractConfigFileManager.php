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

use ArrayIterator;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFileManager;
use Puli\RepositoryManager\Api\InvalidConfigException;
use Puli\RepositoryManager\Api\IOException;
use Webmozart\Glob\Iterator\GlobFilterIterator;

/**
 * Base class for configuration file managers.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractConfigFileManager implements ConfigFileManager
{
    /**
     * {@inheritdoc}
     */
    public function setConfigKey($key, $value)
    {
        $this->getConfig()->set($key, $value);

        $this->saveConfigFile();
    }

    /**
     * {@inheritdoc}
     */
    public function setConfigKeys(array $values)
    {
        $this->getConfig()->merge($values);

        $this->saveConfigFile();
    }

    /**
     * {@inheritdoc}
     */
    public function removeConfigKey($key)
    {
        $this->getConfig()->remove($key);

        $this->saveConfigFile();
    }

    /**
     * {@inheritdoc}
     */
    public function removeConfigKeys(array $keys)
    {
        foreach ($keys as $key) {
            $this->getConfig()->remove($key);
        }

        $this->saveConfigFile();
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKey($key, $default = null, $fallback = false)
    {
        return $this->getConfig()->getRaw($key, $default, $fallback);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKeys($includeFallback = false, $includeUnset = false)
    {
        $values = $this->getConfig()->toFlatRawArray($includeFallback);

        // Reorder the returned values
        $keysInDefaultOrder = Config::getKeys();
        $defaultValues = array_fill_keys($keysInDefaultOrder, null);

        if (!$includeUnset) {
            $defaultValues = array_intersect_key($defaultValues, $values);
        }

        return array_replace($defaultValues, $values);
    }

    /**
     * {@inheritdoc}
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
     * Returns the managed configuration.
     *
     * @return Config The configuration.
     */
    abstract protected function getConfig();

    /**
     * Saves the file containing the managed configuration.
     *
     * @throws InvalidConfigException If the value is invalid.
     * @throws IOException If the file cannot be written.
     */
    abstract protected function saveConfigFile();
}
