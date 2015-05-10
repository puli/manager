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

use OutOfBoundsException;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Util\TwoDimensionalHashMap;

/**
 * A collection of path mappings.
 *
 * Each mapping has a composite key:
 *
 *  * The repository path of the mapping.
 *  * The package that defines the mapping.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PathMappingCollection
{
    /**
     * @var TwoDimensionalHashMap
     */
    private $map;

    /**
     * @var boolean
     */
    private $primaryKeysSorted = false;

    /**
     * Creates the store.
     */
    public function __construct()
    {
        $this->map = new TwoDimensionalHashMap();
    }

    /**
     * Adds a path mapping.
     *
     * @param PathMapping $mapping The path mapping.
     */
    public function add(PathMapping $mapping)
    {
        $this->map->set($mapping->getRepositoryPath(), $mapping->getContainingPackage()->getName(), $mapping);
        $this->primaryKeysSorted = false;
    }

    /**
     * Sets a path mapping for a specific repository path.
     *
     * @param string          $repositoryPath The repository path.
     * @param PathMapping $mapping        The path mapping.
     */
    public function set($repositoryPath, PathMapping $mapping)
    {
        $this->map->set($repositoryPath, $mapping->getContainingPackage()->getName(), $mapping);
        $this->primaryKeysSorted = false;
    }

    /**
     * Removes a path mapping.
     *
     * This method ignores non-existing path mappings.
     *
     * @param string $repositoryPath The repository path of the mapping.
     * @param string $packageName    The package containing the mapping.
     */
    public function remove($repositoryPath, $packageName)
    {
        $this->map->remove($repositoryPath, $packageName);
    }

    /**
     * Returns a path mapping.
     *
     * @param string $repositoryPath The repository path of the mapping.
     * @param string $packageName    The package containing the mapping.
     *
     * @return PathMapping The path mapping.
     *
     * @throws OutOfBoundsException If no path mapping was set for the
     *                              given repository path/package.
     */
    public function get($repositoryPath, $packageName)
    {
        return $this->map->get($repositoryPath, $packageName);
    }

    /**
     * Returns whether a path mapping was set for the given repository
     * path/package.
     *
     * @param string $repositoryPath The repository path of the mapping.
     * @param string $packageName    The package containing the mapping.
     *
     * @return bool Returns `true` if a path mapping was set for the given
     *              repository path/package.
     */
    public function contains($repositoryPath, $packageName = null)
    {
        return $this->map->contains($repositoryPath, $packageName);
    }

    /**
     * Returns all path mappings set for the given repository path.
     *
     * @param string $repositoryPath The repository path of the mapping.
     *
     * @return PathMapping[] The path mappings.
     *
     * @throws OutOfBoundsException If no path mapping was set for the
     *                              given repository path.
     */
    public function listByRepositoryPath($repositoryPath)
    {
        return $this->map->listByPrimaryKey($repositoryPath);
    }

    /**
     * Returns all path mappings set for the given package name.
     *
     * @param string $packageName The package name.
     *
     * @return PathMapping[] The path mappings.
     *
     * @throws OutOfBoundsException If no path mapping was set for the
     *                              given package name.
     */
    public function listByPackageName($packageName)
    {
        $this->lazySortPrimaryKeys();
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
     * @throws OutOfBoundsException If no path mapping was set for the
     *                              given repository path.
     */
    public function getPackageNames($repositoryPath = null)
    {
        return $this->map->getSecondaryKeys($repositoryPath);
    }

    /**
     * Returns the repository paths of all path mappings.
     *
     * @return string[] The repository paths of the stored mappings.
     */
    public function getRepositoryPaths()
    {
        $this->lazySortPrimaryKeys();
        return $this->map->getPrimaryKeys();
    }

    /**
     * Returns the contents of the collection as array.
     *
     * @return PathMapping[][] An array containing all path mappings indexed
     *                         first by repository, then by package name.
     */
    public function toArray()
    {
        $this->lazySortPrimaryKeys();
        return $this->map->toArray();
    }

    /**
     * Returns whether the collection is empty.
     *
     * @return bool Returns `true` if the collection is empty and `false`
     *              otherwise.
     */
    public function isEmpty()
    {
        return $this->map->isEmpty();
    }

    /**
     * Sorts the map primary keys, if necessary.
     */
    private function lazySortPrimaryKeys()
    {
        if( $this->primaryKeysSorted === true ){
            return;
        }
        $this->map->sortPrimaryKeys();
        $this->primaryKeysSorted = true;
    }
}
