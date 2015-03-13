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

use Exception;
use Puli\Repository\Api\EditableRepository;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Api\Repository\RepositoryManager;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Api\Repository\ResourceMappingState;
use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Conflict\OverrideGraph;
use Puli\RepositoryManager\Conflict\PackageConflictDetector;
use Puli\RepositoryManager\Conflict\PackageConflictException;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Puli\RepositoryManager\Repository\Mapping\AddMappingToPackageFile;
use Puli\RepositoryManager\Repository\Mapping\ConflictCollection;
use Puli\RepositoryManager\Repository\Mapping\InsertAll;
use Puli\RepositoryManager\Repository\Mapping\LoadMapping;
use Puli\RepositoryManager\Repository\Mapping\OverrideConflictingPackages;
use Puli\RepositoryManager\Repository\Mapping\RemoveMappingFromPackageFile;
use Puli\RepositoryManager\Repository\Mapping\ResourceMappingCollection;
use Puli\RepositoryManager\Repository\Mapping\SyncRepositoryPath;
use Puli\RepositoryManager\Repository\Mapping\UnloadMapping;
use Puli\RepositoryManager\Repository\Mapping\UpdateConflicts;
use Puli\RepositoryManager\Transaction\Transaction;

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
     * @var PackageCollection
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
     * @var ResourceMappingCollection
     */
    private $mappings;

    /**
     * @var ConflictCollection
     */
    private $conflicts;

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
        Assert::boolean($failIfNotFound, 'The argument $failIfNotFound must be a boolean.');

        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        $tx = new Transaction();

        try {
            $syncOp = $this->syncRepositoryPath($mapping->getRepositoryPath());
            $syncOp->takeSnapshot();

            $tx->execute($this->loadResourceMapping($mapping, $this->rootPackage));

            if ($failIfNotFound) {
                $this->assertNoLoadErrors($mapping);
            }

            $tx->execute($this->updateConflicts($mapping->listRepositoryPaths()));
            $tx->execute($this->overrideConflictingPackages($mapping));
            $tx->execute($this->updateConflicts());
            $tx->execute($this->addMappingToPackageFile($mapping));
            $tx->execute($syncOp);

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeResourceMapping($repositoryPath)
    {
        Assert::path($repositoryPath);

        if (!$this->rootPackageFile->hasResourceMapping($repositoryPath)) {
            return;
        }

        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        $mapping = $this->mappings->get($repositoryPath, $this->rootPackage->getName());

        $tx = new Transaction();

        try {
            $syncOp = $this->syncRepositoryPath($repositoryPath);
            $syncOp->takeSnapshot();

            $tx->execute($this->unloadResourceMapping($mapping));
            $tx->execute($this->removeMappingFromPackageFile($repositoryPath));
            $tx->execute($syncOp);

            $this->saveRootPackageFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }

        $this->removeResolvedConflicts();
    }

    /**
     * {@inheritdoc}
     */
    public function hasResourceMapping($repositoryPath)
    {
        Assert::path($repositoryPath);

        return $this->rootPackageFile->hasResourceMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceMapping($repositoryPath)
    {
        Assert::path($repositoryPath);

        return $this->rootPackageFile->getResourceMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceMappings($packageName = null, $state = null)
    {
        Assert::nullOrOneOf($state, ResourceMappingState::all(), 'Expected a valid resource mapping state. Got: %s');

        $this->assertMappingsLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $mappings = array();

        Assert::allString($packageNames, 'The package names must be strings. Got: %s');

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

        return array_values($this->conflicts->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function buildRepository()
    {
        $this->assertMappingsLoaded();
        $this->assertRepositoryLoaded();

        $this->insertAll()->execute();
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
        $this->overrideGraph = OverrideGraph::forPackages($this->packages);
        $this->conflictDetector = new PackageConflictDetector($this->overrideGraph);
        $this->mappings = new ResourceMappingCollection();
        $this->conflicts = new ConflictCollection();

        // Load mappings
        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getResourceMappings() as $mapping) {
                $this->loadResourceMapping($mapping, $package)->execute();
            }
        }

        // Scan all paths for conflicts
        $this->updateConflicts($this->mappings->getRepositoryPaths())->execute();
    }

    private function addMappingToPackageFile(ResourceMapping $mapping)
    {
        return new AddMappingToPackageFile($mapping, $this->rootPackageFile);
    }

    private function removeMappingFromPackageFile($repositoryPath)
    {
        return new RemoveMappingFromPackageFile($repositoryPath, $this->rootPackageFile);
    }

    private function loadResourceMapping(ResourceMapping $mapping, Package $package)
    {
        return new LoadMapping($mapping, $package, $this->packages, $this->mappings, $this->conflictDetector);
    }

    private function unloadResourceMapping(ResourceMapping $mapping)
    {
        return new UnloadMapping($mapping, $this->packages, $this->mappings, $this->conflictDetector);
    }

    private function syncRepositoryPath($repositoryPath)
    {
        return new SyncRepositoryPath($repositoryPath, $this->repo, $this->mappings, $this->overrideGraph);
    }

    private function insertAll()
    {
        return new InsertAll($this->repo, $this->mappings, $this->overrideGraph);
    }

    private function updateConflicts(array $repositoryPaths = array())
    {
        return new UpdateConflicts($repositoryPaths, $this->conflictDetector, $this->conflicts, $this->mappings);
    }

    private function overrideConflictingPackages(ResourceMapping $mapping)
    {
        return new OverrideConflictingPackages($mapping, $this->rootPackage, $this->overrideGraph);
    }

    private function saveRootPackageFile()
    {
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    private function removeResolvedConflicts()
    {
        foreach ($this->conflicts as $conflictPath => $conflict) {
            if ($conflict->isResolved()) {
                unset($this->conflicts[$conflictPath]);
            }
        }
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

    private function assertNoLoadErrors(ResourceMapping $mapping)
    {
        $loadErrors = $mapping->getLoadErrors();

        if (count($loadErrors) > 0) {
            // Rethrow first error
            throw reset($loadErrors);
        }
    }
}
