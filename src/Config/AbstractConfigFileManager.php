<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Config;

use ArrayIterator;
use Exception;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFileManager;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\IOException;
use Puli\Manager\Assert\Assert;
use Webmozart\Glob\Iterator\RegexFilterIterator;

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
        $config = $this->getConfig();
        $previouslySet = $config->contains($key);
        $previousValue = $config->get($key);

        if ($previouslySet && $previousValue === $value) {
            return;
        }

        $config->set($key, $value);

        try {
            $this->saveConfigFile();
        } catch (Exception $e) {
            if ($previouslySet) {
                $config->set($key, $previousValue);
            } else {
                $config->remove($key);
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setConfigKeys(array $values)
    {
        $config = $this->getConfig();
        $previouslyUnset = array();
        $previousValues = array();

        foreach ($values as $key => $value) {
            if ($config->contains($key)) {
                $previousValues[$key] = $config->get($key);
            } else {
                $previouslyUnset[] = $key;
            }
        }

        $config->merge($values);

        try {
            $this->saveConfigFile();
        } catch (Exception $e) {
            foreach ($previousValues as $key => $previousValue) {
                $config->set($key, $previousValue);
            }
            foreach ($previouslyUnset as $key) {
                $config->remove($key);
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeConfigKey($key)
    {
        $config = $this->getConfig();

        if (!$config->contains($key)) {
            return;
        }

        $previousValue = $config->get($key);
        $config->remove($key);

        try {
            $this->saveConfigFile();
        } catch (Exception $e) {
            $config->set($key, $previousValue);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeConfigKeys(array $keys)
    {
        $config = $this->getConfig();
        $previousValues = array();

        foreach ($keys as $key) {
            if ($config->contains($key)) {
                $previousValues[$key] = $config->get($key);
                $config->remove($key);
            }
        }

        try {
            $this->saveConfigFile();
        } catch (Exception $e) {
            foreach ($previousValues as $key => $previousValue) {
                $config->set($key, $previousValue);
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearConfigKeys()
    {
        $config = $this->getConfig();
        $previousValues = array();

        foreach ($config->toArray(false) as $key => $value) {
            $previousValues[$key] = $value;
        }

        $config->clear();

        try {
            $this->saveConfigFile();
        } catch (Exception $e) {
            foreach ($previousValues as $key => $previousValue) {
                $config->set($key, $previousValue);
            }

            throw $e;
        }

    }

    /**
     * {@inheritdoc}
     */
    public function hasConfigKey($key, $fallback = false)
    {
        Assert::boolean($fallback, 'The argument $fallback must be a boolean.');

        return $this->getConfig()->contains($key, $fallback);
    }

    /**
     * {@inheritdoc}
     */
    public function hasConfigKeys($fallback = false)
    {
        return !$this->getConfig()->isEmpty($fallback);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKey($key, $default = null, $fallback = false)
    {
        Assert::boolean($fallback, 'The argument $fallback must be a boolean.');

        return $this->getConfig()->getRaw($key, $default, $fallback);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKeys($includeFallback = false, $includeUnset = false)
    {
        Assert::boolean($includeFallback, 'The argument $includeFallback must be a boolean.');
        Assert::boolean($includeUnset, 'The argument $includeUnset must be a boolean.');

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
        Assert::string($pattern, 'The pattern must be a string.');
        Assert::boolean($includeFallback, 'The argument $includeFallback must be a boolean.');
        Assert::boolean($includeUnset, 'The argument $includeUnset must be a boolean.');

        $values = $this->getConfigKeys($includeFallback, $includeUnset);

        $regEx = '~^'.str_replace('\\*', '.*', preg_quote($pattern, '~')).'$~';
        $staticPrefix = $pattern;

        if (false !== $pos = strpos($staticPrefix, '*')) {
            $staticPrefix = substr($staticPrefix, 0, $pos);
        }

        $iterator = new RegexFilterIterator(
            $regEx,
            $staticPrefix,
            new ArrayIterator($values),
            RegexFilterIterator::FILTER_KEY | RegexFilterIterator::KEY_AS_KEY
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
