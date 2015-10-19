<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Repository\Mapping;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Puli\Manager\Api\Repository\PathConflict;

/**
 * A collection of resource path conflicts.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConflictCollection implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @var PathConflict[]
     */
    private $conflicts = array();

    /**
     * Add a path conflict to the collection.
     *
     * @param PathConflict $conflict The path conflict
     */
    public function add(PathConflict $conflict)
    {
        $this->conflicts[$conflict->getRepositoryPath()] = $conflict;
    }

    /**
     * Check whether or not the collection contains a conflict for the given repository path.
     *
     * @param string $repositoryPath The repository path
     *
     * @return bool Returns `true` if the collection contains a conflict for the given repository path.
     */
    public function has($repositoryPath)
    {
        return isset($this->conflicts[$repositoryPath]);
    }

    /**
     * Get a path conflict.
     *
     * @param string $repositoryPath The repository path
     *
     * @throws OutOfBoundsException If the collection does not contain a conflict for the given repository path.
     *
     * @return PathConflict The path conflict
     */
    public function get($repositoryPath)
    {
        if (!isset($this->conflicts[$repositoryPath])) {
            throw new OutOfBoundsException(sprintf(
                'No conflict is stored for the path "%s".',
                $repositoryPath
            ));
        }

        return $this->conflicts[$repositoryPath];
    }

    /**
     * Remove a path conflict from the collection.
     *
     * @param string $repositoryPath The repository path
     */
    public function remove($repositoryPath)
    {
        unset($this->conflicts[$repositoryPath]);
    }

    /**
     * Remove all conflicts from the collection.
     */
    public function clear()
    {
        $this->conflicts = array();
    }

    /**
     * Get the collection as an array.
     *
     * @return PathConflict[]
     */
    public function toArray()
    {
        return $this->conflicts;
    }

    /**
     * Get all the repository paths that have a conflict.
     *
     * @return string[]
     */
    public function getRepositoryPaths()
    {
        return array_keys($this->conflicts);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($repositoryPath)
    {
        return $this->has($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($repositoryPath)
    {
        return $this->get($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $conflict)
    {
        if (null !== $offset) {
            throw new InvalidArgumentException('Offsets are not supported.');
        }

        $this->add($conflict);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($repositoryPath)
    {
        $this->remove($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->conflicts);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->conflicts);
    }
}
