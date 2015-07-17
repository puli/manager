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

    public function add(PathConflict $conflict)
    {
        $this->conflicts[$conflict->getRepositoryPath()] = $conflict;
    }

    public function has($repositoryPath)
    {
        return isset($this->conflicts[$repositoryPath]);
    }

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

    public function remove($repositoryPath)
    {
        unset($this->conflicts[$repositoryPath]);
    }

    public function clear()
    {
        $this->conflicts = array();
    }

    public function toArray()
    {
        return $this->conflicts;
    }

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
