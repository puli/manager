<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use Puli\RepositoryManager\Assert\Assert;

/**
 * A conflict when different resource mappings map the same repository path.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryPathConflict
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var ResourceMapping[]
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
     * Adds a resource mapping involved in the conflict.
     *
     * @param ResourceMapping $mapping The resource mapping to add.
     *
     * @throws MappingNotLoadedException If the passed mapping is not loaded.
     */
    public function addMapping(ResourceMapping $mapping)
    {
        if (!$mapping->isLoaded()) {
            throw new MappingNotLoadedException('The passed mapping must be loaded.');
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
     * Removes a resource mapping from the conflict.
     *
     * If only one resource mapping is left after removing this mapping, that
     * mapping is removed as well. The conflict is then resolved.
     *
     * @param ResourceMapping $mapping The resource mapping to remove.
     *
     * @throws MappingNotLoadedException If the passed mapping is not loaded.
     */
    public function removeMapping(ResourceMapping $mapping)
    {
        if (!$mapping->isLoaded()) {
            throw new MappingNotLoadedException('The passed mapping must be loaded.');
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
     * Returns the resource mappings involved in the conflict.
     *
     * @return ResourceMapping[]
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
