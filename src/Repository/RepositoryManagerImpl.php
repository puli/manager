<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Repository;

use Exception;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\PathMappingState;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Conflict\OverrideGraph;
use Puli\Manager\Conflict\PackageConflictDetector;
use Puli\Manager\Conflict\PackageConflictException;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Repository\Mapping\AddPathMappingToPackageFile;
use Puli\Manager\Repository\Mapping\ConflictCollection;
use Puli\Manager\Repository\Mapping\PopulateRepository;
use Puli\Manager\Repository\Mapping\LoadPathMapping;
use Puli\Manager\Repository\Mapping\OverrideConflictingPackages;
use Puli\Manager\Repository\Mapping\RemovePathMappingFromPackageFile;
use Puli\Manager\Repository\Mapping\PathMappingCollection;
use Puli\Manager\Repository\Mapping\SyncRepositoryPath;
use Puli\Manager\Repository\Mapping\UnloadPathMapping;
use Puli\Manager\Repository\Mapping\UpdateConflicts;
use Puli\Manager\Transaction\Transaction;
use Puli\Repository\Api\EditableRepository;

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
     * @var PathMappingCollection
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
     * @param EditableRepository $repo
     * @param PackageCollection  $packages
     * @param PackageFileStorage $packageFileStorage
     */
    public function __construct(ProjectEnvironment $environment, EditableRepository $repo, PackageCollection $packages, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->repo = $repo;
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
    public function addPathMapping(PathMapping $mapping, $failIfNotFound = true)
    {
        Assert::boolean($failIfNotFound, 'The argument $failIfNotFound must be a boolean.');

        $this->assertMappingsLoaded();

        $tx = new Transaction();

        try {
            $syncOp = $this->syncRepositoryPath($mapping->getRepositoryPath());
            $syncOp->takeSnapshot();

            $tx->execute($this->loadPathMapping($mapping, $this->rootPackage));

            if ($failIfNotFound) {
                $this->assertNoLoadErrors($mapping);
            }

            $tx->execute($this->updateConflicts($mapping->listRepositoryPaths()));
            $tx->execute($this->overrideConflictingPackages($mapping));
            $tx->execute($this->updateConflicts());
            $tx->execute($this->addPathMappingToPackageFile($mapping));
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
    public function removePathMapping($repositoryPath)
    {
        Assert::path($repositoryPath);

        if (!$this->rootPackageFile->hasPathMapping($repositoryPath)) {
            return;
        }

        $this->assertMappingsLoaded();

        $mapping = $this->mappings->get($repositoryPath, $this->rootPackage->getName());

        $tx = new Transaction();

        try {
            $syncOp = $this->syncRepositoryPath($repositoryPath);
            $syncOp->takeSnapshot();

            $tx->execute($this->unloadPathMapping($mapping));
            $tx->execute($this->removePathMappingFromPackageFile($repositoryPath));
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
    public function hasPathMapping($repositoryPath)
    {
        Assert::path($repositoryPath);

        return $this->rootPackageFile->hasPathMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMapping($repositoryPath)
    {
        Assert::path($repositoryPath);

        return $this->rootPackageFile->getPathMapping($repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMappings($packageName = null, $state = null)
    {
        Assert::nullOrOneOf($state, PathMappingState::all(), 'Expected a valid path mapping state. Got: %s');

        $this->assertMappingsLoaded();

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $mappings = array();

        Assert::allString($packageNames, 'The package names must be strings. Got: %s');

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getPathMappings() as $mapping) {
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

        $this->populateRepository()->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function clearRepository()
    {
        $this->repo->clear();
    }

    private function loadPathMappings()
    {
        $this->overrideGraph = OverrideGraph::forPackages($this->packages);
        $this->conflictDetector = new PackageConflictDetector($this->overrideGraph);
        $this->mappings = new PathMappingCollection();
        $this->conflicts = new ConflictCollection();

        // Load mappings
        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getPathMappings() as $mapping) {
                $this->loadPathMapping($mapping, $package)->execute();
            }
        }

        // Scan all paths for conflicts
        $this->updateConflicts($this->mappings->getRepositoryPaths())->execute();
    }

    private function addPathMappingToPackageFile(PathMapping $mapping)
    {
        return new AddPathMappingToPackageFile($mapping, $this->rootPackageFile);
    }

    private function removePathMappingFromPackageFile($repositoryPath)
    {
        return new RemovePathMappingFromPackageFile($repositoryPath, $this->rootPackageFile);
    }

    private function loadPathMapping(PathMapping $mapping, Package $package)
    {
        return new LoadPathMapping($mapping, $package, $this->packages, $this->mappings, $this->conflictDetector);
    }

    private function unloadPathMapping(PathMapping $mapping)
    {
        return new UnloadPathMapping($mapping, $this->packages, $this->mappings, $this->conflictDetector);
    }

    private function syncRepositoryPath($repositoryPath)
    {
        return new SyncRepositoryPath($repositoryPath, $this->repo, $this->mappings, $this->overrideGraph);
    }

    private function populateRepository()
    {
        return new PopulateRepository($this->repo, $this->mappings, $this->overrideGraph);
    }

    private function updateConflicts(array $repositoryPaths = array())
    {
        return new UpdateConflicts($repositoryPaths, $this->conflictDetector, $this->conflicts, $this->mappings);
    }

    private function overrideConflictingPackages(PathMapping $mapping)
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
            $this->loadPathMappings();
        }
    }

    private function assertNoLoadErrors(PathMapping $mapping)
    {
        $loadErrors = $mapping->getLoadErrors();

        if (count($loadErrors) > 0) {
            // Rethrow first error
            throw reset($loadErrors);
        }
    }
}
