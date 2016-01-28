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

use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\NoDirectoryException;
use Puli\Manager\Conflict\ModuleConflictException;
use Puli\Repository\Api\EditableRepository;
use Webmozart\Expression\Expression;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RepositoryManager
{
    /**
     * Flag: Override existing path mappings.
     */
    const OVERRIDE = 1;

    /**
     * Flag: Ignore if the referenced files are not found in {@link addPathMapping()}.
     */
    const IGNORE_FILE_NOT_FOUND = 2;

    /**
     * Returns the manager's context.
     *
     * @return ProjectContext The project context.
     */
    public function getContext();

    /**
     * Returns the managed repository.
     *
     * @return EditableRepository The managed repository.
     */
    public function getRepository();

    /**
     * Adds a path mapping to the repository.
     *
     * @param PathMapping $mapping The path mapping.
     * @param int         $flags   A bitwise combination of the flag constants
     *                             in this class.
     *
     * @throws DuplicatePathMappingException If the repository path is already
     *                                       mapped in the root module.
     */
    public function addRootPathMapping(PathMapping $mapping, $flags = 0);

    /**
     * Removes a path mapping from the repository.
     *
     * The path mapping is removed from the root module file. If the mapping
     * is not found, this method does nothing.
     *
     * @param string $repositoryPath The repository path.
     */
    public function removeRootPathMapping($repositoryPath);

    /**
     * Removes all path mappings from the repository that match the given
     * expression.
     *
     * If no mapping is found, this method does nothing.
     *
     * @param Expression $expr The search criteria.
     */
    public function removeRootPathMappings(Expression $expr);

    /**
     * Removes all path mappings from the repository.
     *
     * If no mapping is found, this method does nothing.
     */
    public function clearRootPathMappings();

    /**
     * Returns the path mapping for a repository path in the root module.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return PathMapping The corresponding path mapping.
     *
     * @throws NoSuchPathMappingException If the repository path is not mapped
     *                                    in the given module.
     */
    public function getRootPathMapping($repositoryPath);

    /**
     * Returns all path mappings in the root module that match the given
     * expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return PathMapping[] The path mappings matching the expression.
     */
    public function findRootPathMappings(Expression $expr);

    /**
     * Returns all path mappings in the root module.
     *
     * @return PathMapping[] The path mappings.
     */
    public function getRootPathMappings();

    /**
     * Returns whether a repository path is mapped in the root module.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return bool Returns `true` if the repository path is mapped in the root
     *              module and `false` otherwise.
     */
    public function hasRootPathMapping($repositoryPath);

    /**
     * Returns whether the manager has any path mappings in the root module.
     *
     * You can optionally pass an expression to check whether the manager has
     * path mappings matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has path mappings in the root
     *              module and `false` otherwise. If an expression was passed,
     *              this method only returns `true` if the manager has path
     *              mappings matching the expression.
     */
    public function hasRootPathMappings(Expression $expr = null);

    /**
     * Returns the path mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     * @param string $moduleName     The name of the containing module.
     *
     * @return PathMapping The corresponding path mapping.
     *
     * @throws NoSuchPathMappingException If the repository path is not mapped
     *                                    in the given module.
     */
    public function getPathMapping($repositoryPath, $moduleName);

    /**
     * Returns all path mappings.
     *
     * @return PathMapping[] The path mappings.
     */
    public function getPathMappings();

    /**
     * Returns all path mappings matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return PathMapping[] The path mappings matching the expression.
     */
    public function findPathMappings(Expression $expr);

    /**
     * Returns whether a repository path is mapped.
     *
     * @param string $repositoryPath The repository path.
     * @param string $moduleName     The name of the containing module.
     *
     * @return bool Returns `true` if the repository path is mapped in the given
     *              module and `false` otherwise.
     */
    public function hasPathMapping($repositoryPath, $moduleName);

    /**
     * Returns whether the manager has any path mappings.
     *
     * You can optionally pass an expression to check whether the manager has
     * path mappings matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has path mappings and `false`
     *              otherwise. If an expression was passed, this method only
     *              returns `true` if the manager has path mappings matching the
     *              expression.
     */
    public function hasPathMappings(Expression $expr = null);

    /**
     * Returns all path conflicts.
     *
     * @return PathConflict[] The path conflicts.
     */
    public function getPathConflicts();

    /**
     * Builds the resource repository.
     *
     * @throws NoDirectoryException    If the dump directory exists and is not a
     *                                 directory.
     * @throws ModuleConflictException If two modules contain conflicting
     *                                 resource definitions.
     */
    public function buildRepository();

    /**
     * Clears the contents of the resource repository.
     */
    public function clearRepository();
}
