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
use Exception;
use Puli\Repository\Api\EditableRepository;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Api\Repository\RepositoryManager;
use Puli\RepositoryManager\Api\Repository\RepositoryNotEmptyException;
use Puli\RepositoryManager\Api\Repository\RepositoryPathConflict;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Conflict\OverrideGraph;
use Puli\RepositoryManager\Conflict\PackageConflict;
use Puli\RepositoryManager\Conflict\PackageConflictDetector;
use Puli\RepositoryManager\Conflict\PackageConflictException;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Puli\RepositoryManager\Repository\Iterator\RecursivePathsIterator;
use RecursiveIteratorIterator;
use Webmozart\PathUtil\Path;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManagerImpl implements  RepositoryManager
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
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function addResourceMapping(ResourceMapping $mapping, $failIfNotFound = true)
    {
        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        $added = false;

        try {
            $this->loadResourceMapping($mapping, $this->rootPackage, $failIfNotFound);

            $this->checkPathsForConflicts($mapping->listRepositoryPaths());
            $this->overrideConflictingPackages($mapping);
            $this->verifyAndResolveConflicts();

            $repoUpdater = new RepositoryUpdater($this->overrideGraph);
            $this->addMappingToRepo($repoUpdater, $mapping);
            $repoUpdater->updateRepository($this->repo);
            $added = true;

            $this->rootPackageFile->addResourceMapping($mapping);
            $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
        } catch (Exception $e) {
            if ($added) {
                $repoUpdater = new RepositoryUpdater($this->overrideGraph);
                $this->removeMappingFromRepo($repoUpdater, $mapping);
                $repoUpdater->updateRepository($this->repo);
            }

            if ($mapping->isLoaded()) {
                $this->unloadResourceMapping($mapping, $this->rootPackage);
            }

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeResourceMapping($repositoryPath)
    {
        if (!$this->rootPackageFile->hasResourceMapping($repositoryPath)) {
            return;
        }

        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        $mapping = $this->mappingStore->get($repositoryPath, $this->rootPackage);
        $previouslyConflicting = $mapping->getConflictingMappings();

        $repoUpdater = new RepositoryUpdater($this->overrideGraph);
        $this->removeMappingFromRepo($repoUpdater, $mapping);
        $this->unloadResourceMapping($mapping, $this->rootPackage);
        $this->addMappingsToRepo($repoUpdater, $previouslyConflicting);
        $repoUpdater->updateRepository($this->repo);

        $this->rootPackageFile->removeResourceMapping($repositoryPath);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

    }

    /**
     * {@inheritdoc}
     */
    public function hasResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->hasResourceMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->getResourceMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getPathConflicts()
    {
        $this->assertMappingsLoaded();

        return array_values($this->conflicts);
    }

    /**
     * {@inheritdoc}
     */
    public function buildRepository()
    {
        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        if ($this->repo->hasChildren('/')) {
            throw new RepositoryNotEmptyException('The repository is not empty.');
        }

        $repoUpdater = new RepositoryUpdater($this->overrideGraph);

        foreach ($this->mappingStore->getRepositoryPaths() as $repositoryPath) {
            foreach ($this->mappingStore->getAll($repositoryPath) as $mapping) {
                $this->addMappingToRepo($repoUpdater, $mapping);
            }
        }

        $repoUpdater->updateRepository($this->repo);
    }

    /**
     * {@inheritdoc}
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
                $this->loadResourceMapping($mapping, $package);
            }
        }

        $this->checkPathsForConflicts($this->mappingStore->getRepositoryPaths());
    }

    private function loadResourceMapping(ResourceMapping $mapping, Package $package, $failIfNotFound = false)
    {
        $mapping->load($package, $this->packages, $failIfNotFound);

        foreach ($mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappingStore->add($repositoryPath, $package, $mapping);
            $this->conflictDetector->claim($repositoryPath, $package->getName());
            $repositoryPaths[] = $repositoryPath;
        }
    }

    private function unloadResourceMapping(ResourceMapping $mapping, Package $package)
    {
        foreach ($mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappingStore->remove($repositoryPath, $package);
            $this->conflictDetector->release($repositoryPath, $package->getName());
        }

        // Unload after iterating, otherwise the paths are gone
        $mapping->unload();

        $this->removeResolvedConflicts();
    }

    private function addMappingToRepo(RepositoryUpdater $repoUpdater, ResourceMapping $mapping)
    {
        if ($mapping->isEnabled()) {
            $repoUpdater->add($mapping, $mapping->getContainingPackage()->getName());
        }
    }

    /**
     * @param RepositoryUpdater $repoUpdater
     * @param ResourceMapping[] $mappings
     */
    private function addMappingsToRepo(RepositoryUpdater $repoUpdater, array $mappings)
    {
        foreach ($mappings as $mapping) {
            if ($mapping->isEnabled()) {
                $repoUpdater->add($mapping, $mapping->getContainingPackage()->getName());
            }
        }
    }

    private function removeMappingFromRepo(RepositoryUpdater $repoUpdater, ResourceMapping $mapping)
    {
        if ($mapping->isEnabled()) {
            $this->repo->remove($mapping->getRepositoryPath());

            // Restore previously overridden mappings
            foreach ($mapping->listRepositoryPaths() as $repositoryPath) {
                foreach ($this->mappingStore->getAll($repositoryPath) as $packageName => $overriddenMapping) {
                    if ($mapping !== $overriddenMapping && $overriddenMapping->isEnabled()) {
                        $repoUpdater->add($overriddenMapping, $packageName);
                    }
                }
            }
        }
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

    private function verifyAndResolveConflicts()
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

    private function overrideConflictingPackages(ResourceMapping $mapping)
    {
        $rootPackageName = $this->rootPackage->getName();

        foreach ($mapping->getConflictingPackages() as $conflictingPackage) {
            $this->rootPackageFile->addOverriddenPackage($conflictingPackage->getName());
            $this->overrideGraph->addEdge($conflictingPackage->getName(), $rootPackageName);
        }
    }
}
