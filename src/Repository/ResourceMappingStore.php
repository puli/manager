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

use OutOfBoundsException;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Util\CompositeKeyStore;

/**
 * A store for resource mappings.
 *
 * Each mapping has a composite key:
 *
 *  * The repository path of the mapping.
 *  * The package that defines the mapping.
 *
 * The store implements transparent merging of mappings defined within different
 * packages, but with the same repository path. If a mapping is requested for a
 * repository path without giving a package name, the last mapping set for that
 * repository path is returned by {@link get()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceMappingStore
{
    /**
     * @var CompositeKeyStore
     */
    private $store;

    /**
     * Creates the store.
     */
    public function __construct()
    {
        $this->store = new CompositeKeyStore();
    }

    /**
     * Adds a resource mapping.
     *
     * The repository path has to be passed explicitly. This way you can store
     * the same mapping under different repository paths (e.g. nested paths
     * of the original path).
     *
     * @param string          $repositoryPath The repository path of the mapping.
     * @param Package         $package        The package defining the resource
     *                                        mapping.
     * @param ResourceMapping $mapping        The resource mapping.
     */
    public function add($repositoryPath, Package $package, ResourceMapping $mapping)
    {
        $this->store->set($repositoryPath, $package->getName(), $mapping);
    }

    /**
     * Removes a resource mapping.
     *
     * This method ignores non-existing resource mappings.
     *
     * @param string  $repositoryPath The repository path of the mapping.
     * @param Package $package        The package containing the mapping.
     */
    public function remove($repositoryPath, Package $package)
    {
        $this->store->remove($repositoryPath, $package->getName());
    }

    /**
     * Returns a resource mapping.
     *
     * If no package is passed, the first mapping set for the repository path is
     * returned.
     *
     * @param string  $repositoryPath The repository path of the mapping.
     * @param Package $package        The package containing the mapping.
     *
     * @return ResourceMapping The resource mapping.
     *
     * @throws OutOfBoundsException If no resource mapping was set for the
     *                              given repository path/package.
     */
    public function get($repositoryPath, Package $package = null)
    {
        if (null !== $package) {
            return $this->store->get($repositoryPath, $package->getName());
        }

        return $this->store->getLast($repositoryPath);
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
    public function getAll($repositoryPath)
    {
        return $this->store->getAll($repositoryPath);
    }

    /**
     * Returns whether a resource mapping was set for the given repository
     * path/package.
     *
     * @param string  $repositoryPath The repository path of the mapping.
     * @param Package $package        The package containing the mapping.
     *
     * @return bool Returns `true` if a resource mapping was set for the given
     *              repository path/package.
     */
    public function exists($repositoryPath, Package $package)
    {
        return $this->store->contains($repositoryPath, $package->getName());
    }

    /**
     * Returns whether a resource mapping was set for the given repository path
     * in any package.
     *
     * @param string $repositoryPath The repository path of the mapping.
     *
     * @return bool Returns `true` if a resource mapping was set for the given
     *              repository path.
     */
    public function existsAny($repositoryPath)
    {
        return $this->store->contains($repositoryPath);
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
    public function getDefiningPackageNames($repositoryPath)
    {
        return $this->store->getSecondaryKeys($repositoryPath);
    }

    /**
     * Returns the repository paths of all resource mappings.
     *
     * @return string[] The repository paths of the stored mappings.
     */
    public function getRepositoryPaths()
    {
        return $this->store->getPrimaryKeys();
    }
}
