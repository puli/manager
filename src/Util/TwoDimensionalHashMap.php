<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Util;

use OutOfBoundsException;

/**
 * An hash-map for values identified by a two-dimensional key.
 *
 * Every value in the store has a primary key and a secondary key. When adding
 * values to the store, both keys need to be defined. When retrieving values
 * from the store, you can either get the value for a composite key or all
 * values for the primary key indexed by their secondary keys.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TwoDimensionalHashMap
{
    /**
     * @var array[]
     */
    private $values = array();

    /**
     * Sets a value in the store.
     *
     * @param int|string $primaryKey   The primary key.
     * @param int|string $secondaryKey The secondary key.
     * @param mixed      $value        The value.
     */
    public function set($primaryKey, $secondaryKey, $value)
    {
        if (!isset($this->values[$primaryKey])) {
            $this->values[$primaryKey] = array();
        }

        $this->values[$primaryKey][$secondaryKey] = $value;
    }

    /**
     * Removes a value from the store.
     *
     * This method ignores non-existing keys.
     *
     * @param int|string $primaryKey   The primary key.
     * @param int|string $secondaryKey The secondary key.
     */
    public function remove($primaryKey, $secondaryKey)
    {
        unset($this->values[$primaryKey][$secondaryKey]);

        if (isset($this->values[$primaryKey]) && 0 === count($this->values[$primaryKey])) {
            unset($this->values[$primaryKey]);
        }
    }

    /**
     * Removes all values for the given primary key.
     *
     * This method ignores non-existing keys.
     *
     * @param int|string $primaryKey The primary key.
     */
    public function removeAll($primaryKey)
    {
        unset($this->values[$primaryKey]);
    }

    /**
     * Returns a value from the store.
     *
     * @param int|string $primaryKey   The primary key.
     * @param int|string $secondaryKey The secondary key.
     *
     * @return mixed The value.
     *
     * @throws OutOfBoundsException If no value is set for the given keys.
     */
    public function get($primaryKey, $secondaryKey)
    {
        if (!isset($this->values[$primaryKey][$secondaryKey])) {
            throw new OutOfBoundsException(sprintf(
                'The key ("%s","%s") does not exist.',
                $primaryKey,
                $secondaryKey
            ));
        }

        return $this->values[$primaryKey][$secondaryKey];
    }

    /**
     * Returns whether the store contains the given key(s).
     *
     * The secondary key is optional. If you don't pass it, this method returns
     * `true` if the store contains the given primary key with any secondary
     * key.
     *
     * @param int|string      $primaryKey   The primary key.
     * @param int|string|null $secondaryKey The secondary key.
     *
     * @return bool Returns `true` if the store contains the given key(s).
     */
    public function contains($primaryKey, $secondaryKey = null)
    {
        if (null !== $secondaryKey) {
            return isset($this->values[$primaryKey][$secondaryKey]);
        }

        return isset($this->values[$primaryKey]);
    }

    /**
     * Returns the first value set for the given primary key.
     *
     * @param int|string $primaryKey The primary key.
     *
     * @return mixed The value.
     *
     * @throws OutOfBoundsException If the primary key does not exist.
     */
    public function getFirst($primaryKey)
    {
        if (!isset($this->values[$primaryKey])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $primaryKey
            ));
        }

        return reset($this->values[$primaryKey]);
    }

    /**
     * Returns the last value set for the given primary key.
     *
     * @param int|string $primaryKey The primary key.
     *
     * @return mixed The value.
     *
     * @throws OutOfBoundsException If the primary key does not exist.
     */
    public function getLast($primaryKey)
    {
        if (!isset($this->values[$primaryKey])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $primaryKey
            ));
        }

        return end($this->values[$primaryKey]);
    }

    /**
     * Returns the number of secondary keys set for the given primary key.
     *
     * @param int|string $primaryKey The primary key.
     *
     * @return int The number of secondary keys set for the primary key.
     *
     * @throws OutOfBoundsException If the primary key does not exist.
     */
    public function getCount($primaryKey)
    {
        if (!isset($this->values[$primaryKey])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $primaryKey
            ));
        }

        return count($this->values[$primaryKey]);
    }

    /**
     * Returns all values set for the given primary key.
     *
     * @param int|string $primaryKey The primary key.
     *
     * @return array The values indexed by their secondary keys.
     *
     * @throws OutOfBoundsException If the primary key does not exist.
     */
    public function listByPrimaryKey($primaryKey)
    {
        if (!isset($this->values[$primaryKey])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $primaryKey
            ));
        }

        return $this->values[$primaryKey];
    }

    /**
     * Returns all values set for the given secondary key.
     *
     * @param int|string $secondaryKey The secondary key.
     *
     * @return array The values indexed by their primary keys.
     *
     * @throws OutOfBoundsException If the secondary key does not exist.
     */
    public function listBySecondaryKey($secondaryKey)
    {
        $list = array();

        foreach ($this->values as $primaryKey => $valuesBySecondaryKey) {
            if (isset($valuesBySecondaryKey[$secondaryKey])) {
                $list[$primaryKey] = $valuesBySecondaryKey[$secondaryKey];
            }
        }

        if (!$list) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $secondaryKey
            ));
        }

        return $list;
    }

    /**
     * Returns the secondary keys for the given primary key.
     *
     * @param int|string $primaryKey The primary key.
     *
     * @return array The secondary keys.
     *
     * @throws OutOfBoundsException If the primary key does not exist.
     */
    public function getSecondaryKeys($primaryKey = null)
    {
        if ($primaryKey) {
            if (!isset($this->values[$primaryKey])) {
                throw new OutOfBoundsException(sprintf(
                    'The key "%s" does not exist.',
                    $primaryKey
                ));
            }

            return array_keys($this->values[$primaryKey]);
        }

        $allSecondaryKeys = array();

        foreach ($this->values as $primaryKey => $valuesBySecondaryKey) {
            foreach ($valuesBySecondaryKey as $secondaryKey => $values) {
                $allSecondaryKeys[$secondaryKey] = true;
            }
        }

        return array_keys($allSecondaryKeys);
    }

    /**
     * Returns all primary keys.
     *
     * @return array The primary keys.
     */
    public function getPrimaryKeys()
    {
        return array_keys($this->values);
    }

    /**
     * Returns the contents of the store as array.
     *
     * @return array[] A multi-dimensional array containing all values by
     *                 their primary and secondary keys.
     */
    public function toArray()
    {
        return $this->values;
    }

    /**
     * Returns whether the map is empty.
     *
     * @return bool Returns `true` if the map is empty and `false` otherwise.
     */
    public function isEmpty()
    {
        return 0 === count($this->values);
    }

    /**
     * Sorts the primary keys of the map.
     *
     * @param array $order The keys in the desired order.
     */
    public function sortPrimaryKeys(array $order = null)
    {
        if (!$order) {
            ksort($this->values);

            return;
        }

        $orderedKeys = array_intersect_key(array_flip($order), $this->values);

        $this->values = array_replace($orderedKeys, $this->values);
    }

    /**
     * Sorts the secondary keys of a map entry.
     *
     * @param int|string $primaryKey The primary key.
     * @param array      $order      The keys in the desired order.
     */
    public function sortSecondaryKeys($primaryKey, array $order = null)
    {
        if (!isset($this->values[$primaryKey])) {
            throw new OutOfBoundsException(sprintf(
                'The key "%s" does not exist.',
                $primaryKey
            ));
        }

        if (!$order) {
            ksort($this->values[$primaryKey]);

            return;
        }

        $orderedKeys = array_intersect_key(array_flip($order), $this->values[$primaryKey]);

        $this->values[$primaryKey] = array_replace($orderedKeys, $this->values[$primaryKey]);
    }
}
