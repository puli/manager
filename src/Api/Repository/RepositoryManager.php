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

use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\NoDirectoryException;
use Puli\Manager\Config\Config;
use Puli\Manager\Conflict\PackageConflictException;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RepositoryManager
{
    /**
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment();

    /**
     * Adds a path mapping to the repository.
     *
     * @param PathMapping $mapping The path mapping.
     * @param bool        $failIfNotFound W
     */
    public function addPathMapping(PathMapping $mapping, $failIfNotFound = true);

    /**
     * Removes a path mapping from the repository.
     *
     * @param string $repositoryPath The repository path.
     */
    public function removePathMapping($repositoryPath);

    /**
     * Returns whether a repository path is mapped.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return bool Returns `true` if the repository path is mapped.
     */
    public function hasPathMapping($repositoryPath);

    /**
     * Returns the path mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return PathMapping The corresponding path mapping.
     *
     * @throws NoSuchPathMappingException If the repository path is not mapped.
     */
    public function getPathMapping($repositoryPath);

    /**
     * Returns the path mappings.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     * @param int             $state       The state of the mappings to return.
     *
     * @return PathMapping[] The path mappings.
     */
    public function getPathMappings($packageName = null, $state = null);

    /**
     * @return PathConflict[]
     */
    public function getPathConflicts();

    /**
     * Builds the resource repository.
     *
     * @throws NoDirectoryException If the dump directory exists and is not a
     *                              directory.
     * @throws PackageConflictException If two packages contain conflicting
     *                                  resource definitions.
     */
    public function buildRepository();

    /**
     * Clears the contents of the resource repository.
     */
    public function clearRepository();
}
