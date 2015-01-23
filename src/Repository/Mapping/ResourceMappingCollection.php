<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository\Mapping;

use OutOfBoundsException;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Util\TwoDimensionalHashMap;

/**
 * A collection of resource mappings.
 *
 * Each mapping has a composite key:
 *
 *  * The repository path of the mapping.
 *  * The package that defines the mapping.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceMappingCollection
{
    /**
     * @var TwoDimensionalHashMap
     */
    private $map;

    /**
     * Creates the store.
     */
    public function __construct()
    {
        $this->map = new TwoDimensionalHashMap();
    }

    /**
     * Adds a resource mapping.
     *
     * @param ResourceMapping $mapping The resource mapping.
     */
    public function add(ResourceMapping $mapping)
    {
        $this->map->set($mapping->getRepositoryPath(), $mapping->getContainingPackage()->getName(), $mapping);
        $this->map->sortByPrimaryKeys();
    }

    /**
     * Sets a resource mapping for a specific repository path.
     *
     * @param string          $repositoryPath The repository path.
     * @param ResourceMapping $mapping        The resource mapping.
     */
    public function set($repositoryPath, ResourceMapping $mapping)
    {
        $this->map->set($repositoryPath, $mapping->getContainingPackage()->getName(), $mapping);
        $this->map->sortByPrimaryKeys();
    }

    /**
     * Removes a resource mapping.
     *
     * This method ignores non-existing resource mappings.
     *
     * @param string $repositoryPath The repository path of the mapping.
     * @param string $packageName    The package containing the mapping.
     */
    public function remove($repositoryPath, $packageName)
    {
        $this->map->remove($repositoryPath, $packageName);
    }

    /**
     * Returns a resource mapping.
     *
     * @param string $repositoryPath The repository path of the mapping.
     * @param string $packageName    The package containing the mapping.
     *
     * @return ResourceMapping The resource mapping.
     *
     * @throws OutOfBoundsException If no resource mapping was set for the
     *                              given repository path/package.
     */
    public function get($repositoryPath, $packageName)
    {
        return $this->map->get($repositoryPath, $packageName);
    }

    /**
     * Returns whether a resource mapping was set for the given repository
     * path/package.
     *
     * @param string $repositoryPath The repository path of the mapping.
     * @param string $packageName    The package containing the mapping.
     *
     * @return bool Returns `true` if a resource mapping was set for the given
     *              repository path/package.
     */
    public function contains($repositoryPath, $packageName = null)
    {
        return $this->map->contains($repositoryPath, $packageName);
    }

    /**
     * Returns all resource mappings set for the given repository path.
     *
     * @param string $repositoryPath The repository path of the mapping.
     *
     * @return ResourceMapping[] The resource mappings.
     *
     * @throws OutOfBoundsException If no resource mapping was set for the
     *                              given repository path.
     */
    public function listByRepositoryPath($repositoryPath)
    {
        return $this->map->listByPrimaryKey($repositoryPath);
    }

    /**
     * Returns all resource mappings set for the given package name.
     *
     * @param string $packageName The package name.
     *
     * @return ResourceMapping[] The resource mappings.
     *
     * @throws OutOfBoundsException If no resource mapping was set for the
     *                              given package name.
     */
    public function listByPackageName($packageName)
    {
        return $this->map->listBySecondaryKey($packageName);
    }

    /**
     * Returns the names of the packages defining mappings with the given
     * repository path.
     *
     * @param string $repositoryPath The repository path of the mapping.
     *
     * @return string[] The package names.
     *
     * @throws OutOfBoundsException If no resource mapping was set for the
     *                              given repository path.
     */
    public function getPackageNames($repositoryPath = null)
    {
        return $this->map->getSecondaryKeys($repositoryPath);
    }

    /**
     * Returns the repository paths of all resource mappings.
     *
     * @return string[] The repository paths of the stored mappings.
     */
    public function getRepositoryPaths()
    {
        return $this->map->getPrimaryKeys();
    }

    public function toArray()
    {
        return $this->map->toArray();
    }
}
