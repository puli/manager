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

use Exception;
use InvalidArgumentException;
use Puli\Manager\Api\AlreadyLoadedException;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\NotLoadedException;
use Puli\Manager\Api\Package\NoSuchPackageException;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Assert\Assert;
use RecursiveIteratorIterator;
use Webmozart\Expression\Expression;
use Webmozart\Glob\Iterator\RecursiveDirectoryIterator;
use Webmozart\PathUtil\Path;

/**
 * Maps a repository path to one or more filesystem paths.
 *
 * The filesystem paths are passed in the form of *path references* that are
 * either paths relative to the package's root directory or paths relative
 * to another packages's root directory prefixed with `@vendor/package:`,
 * where "vendor/package" is the name of the referenced package.
 *
 * The path references are turned into absolute filesystem paths when
 * {@link load()} is called.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PathMapping
{
    /**
     * The repository path field in {@link Expression} instances.
     */
    const REPOSITORY_PATH = 'repositoryPath';

    /**
     * The state field in {@link Expression} instances.
     */
    const STATE = 'state';

    /**
     * The package field in {@link Expression} instances.
     */
    const CONTAINING_PACKAGE = 'containingPackage';

    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var string[]
     */
    private $pathReferences = array();

    /**
     * @var string[]
     */
    private $filesystemPaths = array();

    /**
     * @var string[]
     */
    private $pathMappings = array();

    /**
     * @var string[]
     */
    private $repositoryPaths = array();

    /**
     * @var Package
     */
    private $containingPackage;

    /**
     * @var int|null
     */
    private $state;

    /**
     * @var Exception[]
     */
    private $loadErrors = array();

    /**
     * @var PathConflict[]
     */
    private $conflicts = array();

    /**
     * Creates a new path mapping.
     *
     * @param string          $repositoryPath The repository path.
     * @param string|string[] $pathReferences The path references.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($repositoryPath, $pathReferences)
    {
        Assert::path($repositoryPath);

        $pathReferences = (array) $pathReferences;

        Assert::notEmpty($pathReferences, 'At least one filesystem path must be passed.');
        Assert::allString($pathReferences, 'The filesystem paths must be strings. Got: %s');
        Assert::allNotEmpty($pathReferences, 'The filesystem paths must not be empty.');

        $this->repositoryPath = $repositoryPath;
        $this->pathReferences = $pathReferences;
    }

    /**
     * Loads the mapping.
     *
     * @param Package           $containingPackage The package that contains the
     *                                             mapping.
     * @param PackageCollection $packages          A list of packages that can
     *                                             be referenced using
     *                                             `@vendor/package:` prefixes
     *                                             in the path references.
     *
     * @throws AlreadyLoadedException If the mapping is already loaded.
     */
    public function load(Package $containingPackage, PackageCollection $packages)
    {
        if (null !== $this->state) {
            throw new AlreadyLoadedException('The mapping is already loaded.');
        }

        $filesystemPaths = array();
        $loadErrors = array();

        foreach ($this->pathReferences as $relativePath) {
            $loadError = null;

            try {
                $absolutePath = $this->makeAbsolute($relativePath, $containingPackage, $packages);
                $this->assertFileExists($absolutePath, $relativePath, $containingPackage);

                $filesystemPaths[] = $absolutePath;
            } catch (NoSuchPackageException $loadError) {
            } catch (FileNotFoundException $loadError) {
            }

            if ($loadError) {
                $loadErrors[] = $loadError;
            }
        }

        foreach ($filesystemPaths as $filesystemPath) {
            $this->pathMappings[$filesystemPath] = $this->repositoryPath;

            if (!is_dir($filesystemPath)) {
                continue;
            }

            $prefixLength = strlen($filesystemPath);
            $directoryEntries = iterator_to_array(new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $filesystemPath,
                    RecursiveDirectoryIterator::CURRENT_AS_PATHNAME | RecursiveDirectoryIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            ));

            // RecursiveDirectoryIterator is not guaranteed to sort its results,
            // so sort them here
            // We need to sort in the loop and not at the very end because the
            // order of the $filesystemPaths should be kept in $pathMappings
            ksort($directoryEntries);

            foreach ($directoryEntries as $nestedFilesystemPath) {
                $this->pathMappings[$nestedFilesystemPath] = substr_replace($nestedFilesystemPath, $this->repositoryPath, 0, $prefixLength);
            }
        }

        $this->repositoryPaths = array_unique($this->pathMappings);
        $this->filesystemPaths = $filesystemPaths;
        $this->loadErrors = $loadErrors;
        $this->containingPackage = $containingPackage;

        sort($this->repositoryPaths);

        $this->refreshState();
    }

    /**
     * Unloads the mapping.
     *
     * This method reverses the effects of {@link load()}. Additionally, all
     * associated conflicts are dereferenced.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function unload()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        $conflictsToRelease = $this->conflicts;

        $this->conflicts = array();

        foreach ($conflictsToRelease as $conflict) {
            $conflict->removeMapping($this);
        }

        $this->filesystemPaths = array();
        $this->pathMappings = array();
        $this->repositoryPaths = array();
        $this->loadErrors = array();
        $this->containingPackage = null;
        $this->state = null;
    }

    /**
     * Returns whether the mapping is loaded.
     *
     * @return bool Returns `true` if {@link load()} was called.
     */
    public function isLoaded()
    {
        return null !== $this->state;
    }

    /**
     * Returns the repository path.
     *
     * @return string The repository path.
     */
    public function getRepositoryPath()
    {
        return $this->repositoryPath;
    }

    /**
     * Returns the path references.
     *
     * The path references refer to filesystem paths. A path reference is
     * either:
     *
     *  * a path relative to the root directory of the containing package;
     *  * a path relative to the root directory of another package, prefixed
     *    with `@vendor/package:`, where "vendor/package" is the name of the
     *    referenced package.
     *
     * @return string[] The path references.
     */
    public function getPathReferences()
    {
        return $this->pathReferences;
    }

    /**
     * Returns the referenced filesystem paths.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return string[] The absolute filesystem paths.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function getFilesystemPaths()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return $this->filesystemPaths;
    }

    /**
     * Lists all filesystem path to repository path mappings of this mapping.
     *
     * @return string[] An array of repository paths with their corresponding
     *                  filesystem paths as keys. If the mapping has multiple
     *                  filesystem paths, then repository paths may occur
     *                  multiple times in the returned array.
     */
    public function listPathMappings()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return $this->pathMappings;
    }

    /**
     * Lists all mapped repository paths.
     *
     * Contrary to {@link getRepositoryPath()}, this array also contains all
     * nested repository paths that are mapped by this mapping.
     *
     * @return string[] A list of all mapped repository paths.
     */
    public function listRepositoryPaths()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return $this->repositoryPaths;
    }

    /**
     * Returns the package that contains the mapping.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return Package The containing package or `null` if the mapping has not
     *                 been loaded.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function getContainingPackage()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return $this->containingPackage;
    }

    /**
     * Returns the errors that occurred during loading of the mapping.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return Exception[] The errors that occurred during loading. If the
     *                     returned array is empty, the mapping was loaded
     *                     successfully.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function getLoadErrors()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return $this->loadErrors;
    }

    /**
     * Adds a conflict to the mapping.
     *
     * A mapping can refer to at most one conflict per conflicting repository
     * path. If the same conflict is added twice, the second addition is
     * ignored. If a different conflict is added for an existing repository
     * path, the previous conflict is removed before adding the new conflict
     * for the repository path.
     *
     * The repository path of the conflict must either be the repository path
     * of the mapping or any path within. If a conflict with a different path
     * is added, an exception is thrown.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @param PathConflict $conflict The conflict to be added.
     *
     * @throws NotLoadedException       If the mapping is not loaded.
     * @throws InvalidArgumentException If the path of the conflict is not
     *                                  within the repository path of the
     *                                  mapping.
     */
    public function addConflict(PathConflict $conflict)
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        if (!Path::isBasePath($this->repositoryPath, $conflict->getRepositoryPath())) {
            throw new InvalidArgumentException(sprintf(
                'The conflicting path %s is not within the path %s of the '.
                'mapping.',
                $conflict->getRepositoryPath(),
                $this->repositoryPath
            ));
        }

        $repositoryPath = $conflict->getRepositoryPath();
        $previousConflict = isset($this->conflicts[$repositoryPath]) ? $this->conflicts[$repositoryPath] : null;

        if ($previousConflict === $conflict) {
            return;
        }

        if ($previousConflict) {
            $previousConflict->removeMapping($this);
        }

        $this->conflicts[$repositoryPath] = $conflict;
        $conflict->addMapping($this);

        $this->refreshState();
    }

    /**
     * Removes a conflict from the mapping.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @param PathConflict $conflict The conflict to remove.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function removeConflict(PathConflict $conflict)
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        $repositoryPath = $conflict->getRepositoryPath();

        if (!isset($this->conflicts[$repositoryPath]) || $conflict !== $this->conflicts[$repositoryPath]) {
            return;
        }

        unset($this->conflicts[$repositoryPath]);
        $conflict->removeMapping($this);

        $this->refreshState();
    }

    /**
     * Returns the conflicts of the mapping.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return PathConflict[] The conflicts.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function getConflicts()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return array_values($this->conflicts);
    }

    /**
     * Returns all packages with conflicting path mappings.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return PackageCollection The conflicting packages.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function getConflictingPackages()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        $collection = new PackageCollection();

        foreach ($this->conflicts as $conflict) {
            foreach ($conflict->getMappings() as $mapping) {
                if ($this === $mapping) {
                    continue;
                }

                $collection->add($mapping->getContainingPackage());
            }
        }

        return $collection;
    }

    /**
     * Returns all conflicting path mappings.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return PathMapping[] The conflicting path mappings.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function getConflictingMappings()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        $conflictingMappings = array();

        foreach ($this->conflicts as $conflict) {
            foreach ($conflict->getMappings() as $mapping) {
                if ($this === $mapping) {
                    continue;
                }

                $conflictingMappings[spl_object_hash($mapping)] = $mapping;
            }
        }

        return array_values($conflictingMappings);
    }

    /**
     * Returns the state of the mapping.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return int One of the {@link PathMappingState} constants.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function getState()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return $this->state;
    }

    /**
     * Returns whether the mapping is enabled.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is
     *              {@link PathMappingState::ENABLED}.
     *
     * @see PathMappingState::ENABLED
     *
     * @throws NotLoadedException If the mapping is not loaded.
     * @throws NotLoadedException If the mapping is not loaded.
     */
    public function isEnabled()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return PathMappingState::ENABLED === $this->state;
    }

    /**
     * Returns whether the path referenced by the mapping was not found.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is
     *              {@link PathMappingState::NOT_FOUND}.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     *
     * @see PathMappingState::NOT_FOUND
     */
    public function isNotFound()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return PathMappingState::NOT_FOUND === $this->state;
    }

    /**
     * Returns whether the mapping conflicts with a mapping in another package.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is
     *              {@link PathMappingState::CONFLICT}.
     *
     * @throws NotLoadedException If the mapping is not loaded.
     *
     * @see PathMappingState::CONFLICT
     */
    public function isConflicting()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The mapping is not loaded.');
        }

        return PathMappingState::CONFLICT === $this->state;
    }

    /**
     * Returns whether the path mapping matches the given expression.
     *
     * @param Expression $expr The search criteria. You can use the fields
     *                         {@link REPOSITORY_PATH}, {@link STATE} and
     *                         {@link CONTAINING_PACKAGE} in the expression.
     *
     * @return bool Returns `true` if the path mapping matches the expression
     *              and `false` otherwise.
     */
    public function match(Expression $expr)
    {
        return $expr->evaluate(array(
            self::REPOSITORY_PATH => $this->repositoryPath,
            self::STATE => $this->state,
            self::CONTAINING_PACKAGE => $this->containingPackage->getName(),
        ));
    }

    private function refreshState()
    {
        if (count($this->conflicts) > 0) {
            $this->state = PathMappingState::CONFLICT;
        } elseif (0 === count($this->filesystemPaths)) {
            $this->state = PathMappingState::NOT_FOUND;
        } else {
            $this->state = PathMappingState::ENABLED;
        }
    }

    private function makeAbsolute($relativePath, Package $containingPackage, PackageCollection $packages)
    {
        // Reference to install path of other package
        if ('@' !== $relativePath[0] || false === ($pos = strpos($relativePath, ':'))) {
            return $containingPackage->getInstallPath().'/'.$relativePath;
        }

        $refPackageName = substr($relativePath, 1, $pos - 1);

        if (!$packages->contains($refPackageName)) {
            throw new NoSuchPackageException(sprintf(
                'The package "%s" referenced in the resource path "%s" was not '.
                'found. Maybe the package is not installed?',
                $refPackageName,
                $relativePath
            ));
        }

        $refPackage = $packages->get($refPackageName);

        return $refPackage->getInstallPath().'/'.substr($relativePath, $pos + 1);
    }

    private function assertFileExists($absolutePath, $relativePath, Package $containingPackage)
    {
        if (!file_exists($absolutePath)) {
            throw new FileNotFoundException(sprintf(
                'The path %s mapped to %s by package "%s" does not exist.',
                $relativePath,
                $this->repositoryPath,
                $containingPackage->getName()
            ));
        }
    }
}
