<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Store;

use OutOfBoundsException;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeKeyStore
{
    private $values = array();

    public function set($key, $secondaryKey, $value)
    {
        if (!isset($this->values[$key])) {
            $this->values[$key] = array();
        }

        $this->values[$key][$secondaryKey] = $value;
    }

    public function get($key, $secondaryKey)
    {
        if (!isset($this->values[$key][$secondaryKey])) {
            throw new OutOfBoundsException(sprintf(
                'The key ("%s","%s") does not exist.',
                $key,
                $secondaryKey
            ));
        }

        return $this->values[$key][$secondaryKey];
    }

    public function contains($key, $secondaryKey = null)
    {
        if (null !== $secondaryKey) {
            return isset($this->values[$key][$secondaryKey]);
        }

        return isset($this->values[$key]);
    }

    public function getFirst($key)
    {
        if (!isset($this->values[$key])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $key
            ));
        }

        return reset($this->values[$key]);
    }

    public function getAll($key)
    {
        if (!isset($this->values[$key])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $key
            ));
        }

        return $this->values[$key];
    }

    public function getCount($key)
    {
        if (!isset($this->values[$key])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $key
            ));
        }

        return count($this->values[$key]);
    }

    public function getKeys()
    {
        return array_keys($this->values);
    }

    public function remove($key, $secondaryKey)
    {
        unset($this->values[$key][$secondaryKey]);

        if (isset($this->values[$key]) && 0 === count($this->values[$key])) {
            unset($this->values[$key]);
        }
    }

    public function removeAll($key)
    {
        unset($this->values[$key]);
    }

    public function toArray()
    {
        return $this->values;
    }
}
