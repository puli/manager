<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Repository;

use Puli\Manager\Api\NotLoadedException;
use Puli\Manager\Assert\Assert;

/**
 * A conflict when different path mappings map the same repository path.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PathConflict
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var PathMapping[]
     */
    private $mappings = array();

    /**
     * Creates the conflict.
     *
     * @param string $repositoryPath The conflicting repository path.
     */
    public function __construct($repositoryPath)
    {
        Assert::path($repositoryPath);

        $this->repositoryPath = $repositoryPath;
    }

    /**
     * Returns the conflicting repository path.
     *
     * @return string The repository path.
     */
    public function getRepositoryPath()
    {
        return $this->repositoryPath;
    }

    /**
     * Adds a path mapping involved in the conflict.
     *
     * @param PathMapping $mapping The path mapping to add.
     *
     * @throws NotLoadedException If the passed mapping is not loaded.
     */
    public function addMapping(PathMapping $mapping)
    {
        if (!$mapping->isLoaded()) {
            throw new NotLoadedException('The passed mapping must be loaded.');
        }

        $packageName = $mapping->getContainingPackage()->getName();
        $previousMapping = isset($this->mappings[$packageName]) ? $this->mappings[$packageName] : null;

        if ($previousMapping === $mapping) {
            return;
        }

        if ($previousMapping) {
            $previousMapping->removeConflict($this);
        }

        $this->mappings[$packageName] = $mapping;
        $mapping->addConflict($this);
    }

    /**
     * Adds path mappings involved in the conflict.
     *
     * @param PathMapping[] $mappings The path mappings to add.
     *
     * @throws NotLoadedException If a passed mapping is not loaded.
     */
    public function addMappings(array $mappings)
    {
        foreach ($mappings as $mapping) {
            $this->addMapping($mapping);
        }
    }

    /**
     * Removes a path mapping from the conflict.
     *
     * If only one path mapping is left after removing this mapping, that
     * mapping is removed as well. The conflict is then resolved.
     *
     * @param PathMapping $mapping The path mapping to remove.
     *
     * @throws NotLoadedException If the passed mapping is not loaded.
     */
    public function removeMapping(PathMapping $mapping)
    {
        if (!$mapping->isLoaded()) {
            throw new NotLoadedException('The passed mapping must be loaded.');
        }

        $packageName = $mapping->getContainingPackage()->getName();

        if (!isset($this->mappings[$packageName]) || $mapping !== $this->mappings[$packageName]) {
            return;
        }

        unset($this->mappings[$packageName]);
        $mapping->removeConflict($this);

        // Conflict was resolved
        if (count($this->mappings) < 2) {
            $resolvedMappings = $this->mappings;
            $this->mappings = array();

            foreach ($resolvedMappings as $resolvedMapping) {
                $resolvedMapping->removeConflict($this);
            }
        }
    }

    /**
     * Returns the path mappings involved in the conflict.
     *
     * @return PathMapping[]
     */
    public function getMappings()
    {
        return $this->mappings;
    }

    /**
     * Resolves the conflict.
     *
     * This method removes all mappings from the conflict. After calling this
     * method, {@link isResolved()} returns `true`.
     */
    public function resolve()
    {
        foreach ($this->mappings as $mapping) {
            $mapping->removeConflict($this);
        }

        $this->mappings = array();
    }

    /**
     * Returns whether the conflict is resolved.
     *
     * @return bool Returns `true` if the conflict has no associated mappings.
     */
    public function isResolved()
    {
        return 0 === count($this->mappings);
    }
}
