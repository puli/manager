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
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Conflict\OverrideGraph;
use Puli\RepositoryManager\Conflict\PackageConflict;
use Puli\RepositoryManager\Conflict\PackageConflictDetector;
use Puli\RepositoryManager\Conflict\PackageConflictException;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageCollection;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Repository\Iterator\RecursivePathsIterator;
use RecursiveIteratorIterator;
use Webmozart\PathUtil\Path;

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
     * @var RepositoryUpdater
     */
    private $repoUpdater;

    /**
     * @var PackageCollection|Package[]
     */
    private $packages;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var PackageConflictDetector
     */
    private $conflictDetector;

    /**
     * @var ResourceMappingStore
     */
    private $mappingStore;

    /**
     * @var RepositoryPathConflict[]
     */
    private $conflicts = array();

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
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
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
     * @param bool            $failIfNotFound W
     */
    public function addResourceMapping(ResourceMapping $mapping, $failIfNotFound = true)
    {
        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        $this->repoUpdater->clear();

        $rootPackageName = $this->rootPackage->getName();

        // true: detect conflicts
        $this->loadResourceMapping($mapping, $this->rootPackage, true, $failIfNotFound);

        foreach ($mapping->getConflictingPackages() as $conflictingPackage) {
            $this->rootPackageFile->addOverriddenPackage($conflictingPackage->getName());
            $this->overrideGraph->addEdge($conflictingPackage->getName(), $rootPackageName);
        }

        $this->resolveConflicts();

        if ($mapping->isEnabled()) {
            $this->repoUpdater->add($mapping, $rootPackageName);
        }

        // Save config file before modifying the repository, so that the
        // repository can be rebuilt *with* the changes on failures
        $this->rootPackageFile->addResourceMapping($mapping);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        // Now update the repository
        $this->repoUpdater->commit();
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

        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        $this->repoUpdater->clear();

        $mapping = $this->mappingStore->get($repositoryPath, $this->rootPackage);
        $wasEnabled = $mapping->isEnabled();
        $this->unloadResourceMapping($mapping, $this->rootPackage);

        // Save config file before modifying the repository, so that the
        // repository can be rebuilt *with* the changes on failures
        $this->rootPackageFile->removeResourceMapping($repositoryPath);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        if ($wasEnabled) {
            $this->repo->remove($repositoryPath);
        }

        $this->repoUpdater->commit();
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
     * @param int             $state       The state of the mappings to return.
     *
     * @return ResourceMapping[] The resource mappings.
     */
    public function getResourceMappings($packageName = null, $state = null)
    {
        $this->assertMappingsLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $mappings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getResourceMappings() as $mapping) {
                if (null === $state || $state === $mapping->getState()) {
                    $mappings[] = $mapping;
                }
            }
        }

        return $mappings;
    }

    /**
     * @return RepositoryPathConflict[]
     */
    public function getPathConflicts()
    {
        $this->assertMappingsLoaded();

        return array_values($this->conflicts);
    }

    /**
     * Builds the resource repository.
     *
     * @throws NoDirectoryException If the dump directory exists and is not a
     *                              directory.
     * @throws PackageConflictException If two packages contain conflicting
     *                                  resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function buildRepository()
    {
        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        if ($this->repo->hasChildren('/')) {
            throw new RepositoryNotEmptyException('The repository is not empty.');
        }

        $this->repoUpdater->clear();

        foreach ($this->mappingStore->getRepositoryPaths() as $repositoryPath) {
            foreach ($this->mappingStore->getAll($repositoryPath) as $packageName => $mapping) {
                if ($mapping->isEnabled()) {
                    $this->repoUpdater->add($mapping, $packageName);
                }
            }
        }

        $this->repoUpdater->commit();
    }

    /**
     * Clears the contents of the resource repository.
     */
    public function clearRepository()
    {
        $this->assertRepositoryLoaded();

        $this->repo->clear();
    }

    private function loadRepository()
    {
        $this->assertMappingsLoaded();

        $this->repo = $this->environment->getRepository();
        $this->repoUpdater = new RepositoryUpdater($this->repo, $this->overrideGraph);
    }

    /**
     * Loads the resource mappings and override settings for the installed
     * packages.
     *
     * The method processes the package configuration files and stores the
     * following information:
     *
     *  * The resources mappings of each package.
     *  * The override dependencies between the packages.
     *
     * Once all that information is loaded, the repository can be built by
     * loading the resource mappings of the packages in the order determined
     * by the override dependencies: First the resources of overridden packages
     * are added to the repository, then the resources of the overriding ones.
     *
     * If two packages map the same resource path without having an override
     * order specified between them, a conflict exception is raised. The
     * override order can be specified either by marking one package to override
     * the other one (see {@link PackageFile::setOverriddenPackages()} or by
     * setting the order between the packages in the root package file
     * (see RootPackageFile::setPackageOrder()}.
     *
     * This method is called automatically when necessary. You can however use
     * it to validate the configuration files of the packages without doing any
     * changes to them.
     *
     * @throws PackageConflictException If a resource conflict is detected.
     */
    private function loadResourceMappings()
    {
        $this->overrideGraph = new OverrideGraph($this->packages->getPackageNames());
        $this->conflictDetector = new PackageConflictDetector($this->overrideGraph);
        $this->mappingStore = new ResourceMappingStore();
        $this->conflicts = array();

        // Prepare override graph
        foreach ($this->packages as $package) {
            $this->loadOverrideOrder($package);
        }

        // Load mappings
        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getResourceMappings() as $mapping) {
                // false: postpone conflict detection
                $this->loadResourceMapping($mapping, $package, false);
            }
        }

        $this->checkPathsForConflicts($this->mappingStore->getRepositoryPaths());
    }

    private function loadResourceMapping(ResourceMapping $mapping, Package $package, $detectConflicts = true, $failIfNotFound = false)
    {
        $mapping->load($package, $this->packages, $failIfNotFound);

        $iterator = $this->getMappingIterator($mapping);
        $repositoryPaths = array();

        foreach ($iterator as $repositoryPath) {
            $this->mappingStore->add($repositoryPath, $package, $mapping);
            $this->conflictDetector->claim($repositoryPath, $package->getName());
            $repositoryPaths[] = $repositoryPath;
        }

        if ($detectConflicts) {
            $this->checkPathsForConflicts($repositoryPaths);
        }
    }

    private function unloadResourceMapping(ResourceMapping $mapping, Package $package)
    {
        $iterator = $this->getMappingIterator($mapping);
        $conflictingMappings = $mapping->getConflictingMappings();

        foreach ($iterator as $repositoryPath) {
            $this->mappingStore->remove($repositoryPath, $package);
            $this->conflictDetector->release($repositoryPath, $package->getName());

            if (!$this->mappingStore->existsAny($repositoryPath)) {
                continue;
            }

            // Reapply previously overridden mappings
            foreach ($this->mappingStore->getAll($repositoryPath) as $packageName => $overriddenMapping) {
                if ($overriddenMapping->isEnabled()) {
                    $this->repoUpdater->add($overriddenMapping, $packageName);
                }
            }
        }

        // Unload after iterating, otherwise the filesystem paths are gone
        $mapping->unload();

        // Reapply previously conflicting mappings
        foreach ($conflictingMappings as $conflictingMapping) {
            if ($conflictingMapping->isEnabled()) {
                $this->repoUpdater->add($conflictingMapping, $conflictingMapping->getContainingPackage()->getName());
            }
        }

        $this->removeResolvedConflicts();
    }

    private function loadOverrideOrder(Package $package)
    {
        foreach ($package->getPackageFile()->getOverriddenPackages() as $overriddenPackage) {
            if ($this->overrideGraph->hasPackageName($overriddenPackage)) {
                $this->overrideGraph->addEdge($overriddenPackage, $package->getName());
            }
        }

        if ($package instanceof RootPackage) {
            // Make sure we have numeric, ascending keys here
            $packageOrder = array_values($package->getPackageFile()->getOverrideOrder());

            // Each package overrides the previous one in the list
            for ($i = 1, $l = count($packageOrder); $i < $l; ++$i) {
                $overriddenPackage = $packageOrder[$i - 1];
                $overridingPackage = $packageOrder[$i];

                if ($this->overrideGraph->hasPackageName($overriddenPackage)) {
                    $this->overrideGraph->addEdge($overriddenPackage, $overridingPackage);
                }
            }
        }
    }

    private function getMappingIterator(ResourceMapping $mapping)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursivePathsIterator(
                new ArrayIterator($mapping->getFilesystemPaths()),
                $mapping->getRepositoryPath()
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );


        return $iterator;
    }

    private function checkPathsForConflicts(array $repositoryPaths)
    {
        $packageConflicts = $this->conflictDetector->detectConflicts($repositoryPaths);

        $this->deduplicatePackageConflicts($packageConflicts);

        foreach ($packageConflicts as $packageConflict) {
            $repositoryPath = $packageConflict->getConflictingToken();
            $resourceConflict = new RepositoryPathConflict($repositoryPath);

            foreach ($packageConflict->getPackageNames() as $packageName) {
                $conflictingMapping = $this->mappingStore->get($repositoryPath, $this->packages[$packageName]);
                $resourceConflict->addMapping($conflictingMapping);
            }

            $this->conflicts[$repositoryPath] = $resourceConflict;
        }
    }

    private function resolveConflicts()
    {
        $repositoryPaths = array_keys($this->conflicts);

        foreach ($this->conflicts as $conflict) {
            $conflict->resolve();
        }

        $this->conflicts = array();

        $this->checkPathsForConflicts($repositoryPaths);
    }

    private function removeResolvedConflicts()
    {
        foreach ($this->conflicts as $conflictPath => $conflict) {
            if ($conflict->isResolved()) {
                unset($this->conflicts[$conflictPath]);
            }
        }
    }

    /**
     * @param PackageConflict[] $packageConflicts
     */
    private function deduplicatePackageConflicts(array &$packageConflicts)
    {
        $indicesByPath = array();
        $indicesToRemove = array();

        foreach ($packageConflicts as $index => $packageConflict) {
            $indicesByPath[$packageConflict->getConflictingToken()] = $index;
        }

        foreach ($indicesByPath as $repositoryPath => $index) {
            foreach ($indicesByPath as $otherPath => $otherIndex) {
                if ($otherPath !== $repositoryPath && Path::isBasePath($otherPath, $repositoryPath)) {
                    $indicesToRemove[$index] = true;
                }
            }
        }

        foreach ($indicesToRemove as $index => $true) {
            unset($packageConflicts[$index]);
        }

        // Reorganize indices
        $packageConflicts = array_values($packageConflicts);
    }

    private function assertMappingsLoaded()
    {
        if (!$this->overrideGraph) {
            $this->loadResourceMappings();
        }
    }

    private function assertRepositoryLoaded()
    {
        if (!$this->repo) {
            $this->loadRepository();
        }
    }
}
