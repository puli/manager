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

use Exception;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigManager;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Storage\StorageException;
use Puli\Manager\Assert\Assert;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * Base class for configuration file managers.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractConfigManager implements ConfigManager
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
        $previousValues = $config->toFlatRawArray(false);

        $config->merge($values);

        try {
            $this->saveConfigFile();
        } catch (Exception $e) {
            $config->replace($previousValues);

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
    public function removeConfigKeys(Expression $expr)
    {
        $config = $this->getConfig();
        $previousValues = $config->toFlatRawArray(false);

        foreach ($previousValues as $key => $value) {
            if ($expr->evaluate($key)) {
                $config->remove($key);
            }
        }

        try {
            $this->saveConfigFile();
        } catch (Exception $e) {
            $config->replace($previousValues);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearConfigKeys()
    {
        $this->removeConfigKeys(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function hasConfigKey($key, $includeFallback = false)
    {
        Assert::boolean($includeFallback, 'The argument $fallback must be a boolean.');

        return $this->getConfig()->contains($key, $includeFallback);
    }

    /**
     * {@inheritdoc}
     */
    public function hasConfigKeys(Expression $expr = null, $includeFallback = false)
    {
        if (!$expr) {
            return !$this->getConfig()->isEmpty($includeFallback);
        }

        foreach ($this->getConfigKeys($includeFallback) as $key => $value) {
            if ($expr->evaluate($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKey($key, $default = null, $fallback = false, $raw = true)
    {
        Assert::boolean($fallback, 'The argument $fallback must be a boolean.');

        if ($raw) {
            return $this->getConfig()->getRaw($key, $default, $fallback);
        }

        return $this->getConfig()->get($key, $default, $fallback);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKeys($includeFallback = false, $includeUnset = false, $raw = true)
    {
        Assert::boolean($includeFallback, 'The argument $includeFallback must be a boolean.');
        Assert::boolean($includeUnset, 'The argument $includeUnset must be a boolean.');

        $values = $raw
            ? $this->getConfig()->toFlatRawArray($includeFallback)
            : $this->getConfig()->toFlatArray($includeFallback);

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
    public function findConfigKeys(Expression $expr, $includeFallback = false, $includeUnset = false, $raw = true)
    {
        Assert::boolean($includeFallback, 'The argument $includeFallback must be a boolean.');
        Assert::boolean($includeUnset, 'The argument $includeUnset must be a boolean.');

        $values = array();

        foreach ($this->getConfigKeys($includeFallback, $includeUnset, $raw) as $key => $value) {
            if ($expr->evaluate($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Saves the file containing the managed configuration.
     *
     * @throws InvalidConfigException If the value is invalid.
     * @throws StorageException       If the file cannot be written.
     */
    abstract protected function saveConfigFile();
}
