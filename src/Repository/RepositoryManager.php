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

use ArrayIterator;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Assert\Assertion;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Graph\PackageNameGraph;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Repository\Iterator\RecursivePathsIterator;
use RecursiveIteratorIterator;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var PackageCollection|Package[]
     */
    private $packages;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PackageNameGraph
     */
    private $conflictGraph;

    /**
     * @var ConflictDetector
     */
    private $conflictDetector;

    /**
     * @var string[][][]
     */
    private $filesystemPaths = array();

    /**
     * @var string[][][]
     */
    private $mappingQueue = array();

    /**
     * @var bool[][]
     */
    private $pathReferences = array();

    /**
     * Creates a repository manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     * @param PackageFileStorage $packageFileStorage
     */
    public function __construct(ProjectEnvironment $environment, PackageCollection $packages, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->config = $environment->getConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->rootPackage = $packages->getRootPackage();
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->repo = $environment->getRepository();
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
        $this->conflictGraph = new PackageNameGraph($this->packages->getPackageNames());
        $this->conflictDetector = new ConflictDetector($this->conflictGraph);

        foreach ($packages as $package) {
            foreach ($package->getPackageFile()->getResourceMappings() as $mapping) {
                $this->loadResourceMapping($mapping, $package);
            }

            $this->loadOverrideOrder($package);
        }

        if ($conflict = $this->conflictDetector->detectConflict()) {
            throw ResourceConflictException::forConflict($conflict);
        }
    }

    /**
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Adds a resource mapping to the repository.
     *
     * @param ResourceMapping $mapping The resource mapping.
     */
    public function addResourceMapping(ResourceMapping $mapping)
    {
        $this->clearMappingQueue();

        $this->loadResourceMapping($mapping, $this->rootPackage);

        $rootPackageName = $this->rootPackage->getName();

        while ($conflictingPackage = $this->getConflictingPackageName($rootPackageName)) {
            $this->rootPackageFile->addOverriddenPackage($conflictingPackage);
            $this->conflictGraph->addEdge($conflictingPackage, $rootPackageName);
        }

        // Save config file before modifying the repository, so that the
        // repository can be rebuilt *with* the changes on failures
        $this->rootPackageFile->addResourceMapping($mapping);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        // Add the resources now
        $this->processMappingQueue();
    }

    /**
     * Removes a resource mapping from the repository.
     *
     * @param string $repositoryPath The repository path.
     */
    public function removeResourceMapping($repositoryPath)
    {
        if (!$this->rootPackageFile->hasResourceMapping($repositoryPath)) {
            return;
        }

        $this->clearMappingQueue();

        $this->unloadResourceMapping($repositoryPath, $this->rootPackage);

        // Save config file before modifying the repository, so that the
        // repository can be rebuilt *with* the changes on failures
        $this->rootPackageFile->removeResourceMapping($repositoryPath);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        $this->repo->remove($repositoryPath);

        // Restore the overridden paths that have been tagged as removed in
        // unloadResourceMapping()
        $this->processMappingQueue();
    }

    /**
     * Returns whether a repository path is mapped.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return bool Returns `true` if the repository path is mapped.
     */
    public function hasResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->hasResourceMapping($repositoryPath);
    }

    /**
     * Returns the resource mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return ResourceMapping The corresponding resource mapping.
     *
     * @throws NoSuchMappingException If the repository path is not mapped.
     */
    public function getResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->getResourceMapping($repositoryPath);
    }

    /**
     * Returns the resource mappings.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     *
     * @return ResourceMapping[] The resource mappings.
     */
    public function getResourceMappings($packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $mappings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getResourceMappings() as $mapping) {
                $mappings[] = $mapping;
            }
        }

        return $mappings;
    }

    /**
     * Builds the resource repository.
     *
     * @throws NoDirectoryException If the dump directory exists and is not a
     *                              directory.
     * @throws ResourceConflictException If two packages contain conflicting
     *                                   resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function buildRepository()
    {
        if ($this->repo->hasChildren('/')) {
            // quit
        }

        $this->mappingQueue = $this->filesystemPaths;

        $this->processMappingQueue();
    }

    /**
     * @param ResourceMapping $mapping
     * @param Package         $package
     */
    private function loadResourceMapping(ResourceMapping $mapping, Package $package)
    {
        $repositoryPath = $mapping->getRepositoryPath();
        $packageName = $package->getName();
        $filesystemPaths = $this->getAbsoluteMappedPaths($mapping, $package);

        if (!isset($this->filesystemPaths[$packageName])) {
            $this->filesystemPaths[$packageName] = array();
            $this->mappingQueue[$packageName] = array();
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursivePathsIterator(new ArrayIterator($filesystemPaths), $repositoryPath),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $filesystemPath => $entryPath) {
            if (!isset($this->pathReferences[$entryPath])) {
                $this->pathReferences[$entryPath] = array();
            }

            $this->conflictDetector->register($entryPath, $packageName);
            $this->conflictDetector->markUnchecked($entryPath);

            // Store referencing package and repository path of the mapping
            $this->pathReferences[$entryPath][$packageName] = $repositoryPath;
        }

        $this->filesystemPaths[$packageName][$repositoryPath] = $filesystemPaths;
        $this->mappingQueue[$packageName][$repositoryPath] = true;
    }

    private function unloadResourceMapping($repositoryPath, Package $package)
    {
        $packageName = $package->getName();
        $filesystemPaths = $this->filesystemPaths[$packageName][$repositoryPath];

        $iterator = new RecursiveIteratorIterator(
            new RecursivePathsIterator(new ArrayIterator($filesystemPaths), $repositoryPath),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $filesystemPath => $entryPath) {
            $this->conflictDetector->unregister($entryPath, $packageName);

            // Remove referencing package
            unset($this->pathReferences[$entryPath][$packageName]);

            // Reapply any overridden mappings
            foreach ($this->pathReferences[$entryPath] as $packageName => $mappingPath) {
                $this->mappingQueue[$packageName][$mappingPath] = true;
            }
        }

        unset($this->filesystemPaths[$packageName][$repositoryPath]);
    }

    private function loadOverrideOrder(Package $package)
    {
        foreach ($package->getPackageFile()->getOverriddenPackages() as $overriddenPackage) {
            if ($this->conflictGraph->hasPackageName($overriddenPackage)) {
                $this->conflictGraph->addEdge($overriddenPackage, $package->getName());
            }
        }

        if ($package instanceof RootPackage) {
            // Make sure we have numeric, ascending keys here
            $packageOrder = array_values($package->getPackageFile()->getPackageOrder());

            // Each package overrides the previous one in the list
            for ($i = 1, $l = count($packageOrder); $i < $l; ++$i) {
                $overriddenPackage = $packageOrder[$i - 1];
                $overridingPackage = $packageOrder[$i];

                if ($this->conflictGraph->hasPackageName($overriddenPackage)) {
                    $this->conflictGraph->addEdge($overriddenPackage, $overridingPackage);
                }
            }
        }
    }

    private function getConflictingPackageName($packageName)
    {
        $conflict = $this->conflictDetector->detectConflict();

        if (!$conflict) {
            return null;
        }

        // We are only interested in conflicts that involve this package
        if (!$conflict->involvesPackage($packageName)) {
            throw ResourceConflictException::forConflict($conflict);
        }

        return $conflict->getOpponent($packageName);
    }

    /**
     * @param string  $relativePath
     * @param Package $package
     *
     * @return null|string
     */
    private function makeAbsolute($relativePath, Package $package)
    {
        // Reference to install path of other package
        if ('@' !== $relativePath[0] || false === ($pos = strpos($relativePath, ':'))) {
            return $package->getInstallPath().'/'.$relativePath;
        }

        $refPackageName = substr($relativePath, 1, $pos - 1);
        $optional = false;

        // Package references can be made optional by prefixing
        // with "@?" instead of just "@"
        // Useful for suggested packages, for example
        if ('?' === $refPackageName[0]) {
            $refPackageName = substr($refPackageName, 1);
            $optional = true;
        }

        if (!$this->packages->contains($refPackageName)) {
            if ($optional) {
                return null;
            }

            throw new ResourceDefinitionException(sprintf(
                'The package "%s" referred to a non-existing '.
                'package "%s" in the resource path "%s". Did you '.
                'forget to require the package "%s"?',
                $package->getName(),
                $refPackageName,
                $relativePath,
                $refPackageName
            ));
        }

        $refPackage = $this->packages->get($refPackageName);

        return $refPackage->getInstallPath().'/'.substr($relativePath, $pos + 1);
    }

    /**
     * @param ResourceMapping $mapping
     * @param Package         $package
     *
     * @return array
     */
    private function getAbsoluteMappedPaths(ResourceMapping $mapping, Package $package)
    {
        $filesystemPaths = array();

        foreach ($mapping->getFilesystemPaths() as $relativePath) {
            $absolutePath = $this->makeAbsolute($relativePath, $package);

            if (null === $absolutePath) {
                continue;
            }

            Assertion::true(file_exists($absolutePath), sprintf(
                'The path %s mapped to %s by package "%s" does not exist.',
                $relativePath,
                $mapping->getRepositoryPath(),
                $package->getName()
            ));

            $filesystemPaths[] = $absolutePath;
        }

        return $filesystemPaths;
    }

    /**
     * @param $filesystemPath
     *
     * @return Resource
     */
    private function createResource($filesystemPath)
    {
        return is_dir($filesystemPath)
            ? new DirectoryResource($filesystemPath)
            : new FileResource($filesystemPath);
    }

    private function clearMappingQueue()
    {
        $this->mappingQueue = array();
    }

    private function processMappingQueue()
    {
        if (!$this->mappingQueue) {
            return;
        }

        $packageNames = array_keys($this->mappingQueue);
        $sortedNames = $this->conflictGraph->getSortedPackageNames($packageNames);

        foreach ($sortedNames as $packageName) {
            foreach ($this->mappingQueue[$packageName] as $repositoryPath => $true) {
                // The filesystem paths should be set, but just in case
                if (!isset($this->filesystemPaths[$packageName][$repositoryPath])) {
                    continue;
                }

                $filesystemPaths = $this->filesystemPaths[$packageName][$repositoryPath];

                foreach ($filesystemPaths as $filesystemPath) {
                    $this->repo->add($repositoryPath, $this->createResource($filesystemPath));
                }
            }
        }
    }
}
