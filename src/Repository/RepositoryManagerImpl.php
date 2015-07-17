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
use Puli\Manager\Api\Event\BuildRepositoryEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Repository\DuplicatePathMappingException;
use Puli\Manager\Api\Repository\NoSuchPathMappingException;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Conflict\OverrideGraph;
use Puli\Manager\Conflict\PackageConflictDetector;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Repository\Mapping\AddPathMappingToPackageFile;
use Puli\Manager\Repository\Mapping\ConflictCollection;
use Puli\Manager\Repository\Mapping\LoadPathMapping;
use Puli\Manager\Repository\Mapping\OverrideConflictingPackages;
use Puli\Manager\Repository\Mapping\PathMappingCollection;
use Puli\Manager\Repository\Mapping\PopulateRepository;
use Puli\Manager\Repository\Mapping\RemovePathMappingFromPackageFile;
use Puli\Manager\Repository\Mapping\SyncRepositoryPath;
use Puli\Manager\Repository\Mapping\UnloadPathMapping;
use Puli\Manager\Repository\Mapping\UpdateConflicts;
use Puli\Manager\Transaction\Transaction;
use Puli\Repository\Api\EditableRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManagerImpl implements RepositoryManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

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
     * @var PathMappingCollection
     */
    private $mappingsByResource;

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
        $this->dispatcher = $environment->getEventDispatcher();
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
    public function getRepository()
    {
        return $this->repo;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootPathMapping(PathMapping $mapping, $flags = 0)
    {
        Assert::integer($flags, 'The argument $flags must be a boolean.');

        $this->assertMappingsLoaded();

        if (!($flags & self::OVERRIDE) && $this->rootPackageFile->hasPathMapping($mapping->getRepositoryPath())) {
            throw DuplicatePathMappingException::forRepositoryPath($mapping->getRepositoryPath(), $this->rootPackage->getName());
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncRepositoryPath($mapping->getRepositoryPath());
            $syncOp->takeSnapshot();

            $tx->execute($this->loadPathMapping($mapping, $this->rootPackage));

            if (!($flags & self::IGNORE_FILE_NOT_FOUND)) {
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
    public function removeRootPathMapping($repositoryPath)
    {
        Assert::path($repositoryPath);

        $this->assertMappingsLoaded();

        if (!$this->mappings->contains($repositoryPath, $this->rootPackage->getName())) {
            return;
        }

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
    public function removeRootPathMappings(Expression $expr)
    {
        $this->assertMappingsLoaded();

        $tx = new Transaction();

        try {
            foreach ($this->getRootPathMappings() as $mapping) {
                if ($mapping->match($expr)) {
                    $syncOp = $this->syncRepositoryPath($mapping->getRepositoryPath());
                    $syncOp->takeSnapshot();

                    $tx->execute($this->unloadPathMapping($mapping));
                    $tx->execute($this->removePathMappingFromPackageFile($mapping->getRepositoryPath()));
                    $tx->execute($syncOp);
                }
            }

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
    public function clearRootPathMappings()
    {
        $this->removeRootPathMappings(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootPathMapping($repositoryPath)
    {
        return $this->getPathMapping($repositoryPath, $this->rootPackage->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function findRootPathMappings(Expression $expr)
    {
        $expr = Expr::same($this->rootPackage->getName(), PathMapping::CONTAINING_PACKAGE)
            ->andX($expr);

        return $this->findPathMappings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootPathMappings()
    {
        $this->assertMappingsLoaded();

        $mappings = array();
        $rootPackageName = $this->rootPackage->getName();

        foreach ($this->mappings->toArray() as $mappingsByPackage) {
            if (isset($mappingsByPackage[$rootPackageName])) {
                $mappings[] = $mappingsByPackage[$rootPackageName];
            }
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootPathMapping($repositoryPath)
    {
        return $this->hasPathMapping($repositoryPath, $this->rootPackage->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootPathMappings(Expression $expr = null)
    {
        $expr2 = Expr::same($this->rootPackage->getName(), PathMapping::CONTAINING_PACKAGE);

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasPathMappings($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMapping($repositoryPath, $packageName)
    {
        Assert::string($repositoryPath, 'The repository path must be a string. Got: %s');
        Assert::string($packageName, 'The package name must be a string. Got: %s');

        $this->assertMappingsLoaded();

        if (!$this->mappings->contains($repositoryPath, $packageName)) {
            throw NoSuchPathMappingException::forRepositoryPathAndPackage($repositoryPath, $packageName);
        }

        return $this->mappings->get($repositoryPath, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMappings()
    {
        $this->assertMappingsLoaded();

        $mappings = array();

        foreach ($this->mappings->toArray() as $mappingsByPackage) {
            foreach ($mappingsByPackage as $mapping) {
                $mappings[] = $mapping;
            }
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function findPathMappings(Expression $expr)
    {
        $this->assertMappingsLoaded();

        $mappings = array();

        foreach ($this->mappings->toArray() as $mappingsByPackage) {
            foreach ($mappingsByPackage as $mapping) {
                if ($mapping->match($expr)) {
                    $mappings[] = $mapping;
                }
            }
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPathMapping($repositoryPath, $packageName)
    {
        Assert::string($repositoryPath, 'The repository path must be a string. Got: %s');
        Assert::string($packageName, 'The package name must be a string. Got: %s');

        $this->assertMappingsLoaded();

        return $this->mappings->contains($repositoryPath, $packageName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasPathMappings(Expression $expr = null)
    {
        $this->assertMappingsLoaded();

        if (!$expr) {
            return !$this->mappings->isEmpty();
        }

        foreach ($this->mappings->toArray() as $mappingsByPackage) {
            foreach ($mappingsByPackage as $mapping) {
                if ($mapping->match($expr)) {
                    return true;
                }
            }
        }

        return false;
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

        if ($this->dispatcher->hasListeners(PuliEvents::PRE_BUILD_REPOSITORY)) {
            $event = new BuildRepositoryEvent($this);

            $this->dispatcher->dispatch(PuliEvents::PRE_BUILD_REPOSITORY, $event);

            if ($event->isBuildSkipped()) {
                return;
            }
        }

        $this->populateRepository()->execute();

        if ($this->dispatcher->hasListeners(PuliEvents::POST_BUILD_REPOSITORY)) {
            $this->dispatcher->dispatch(PuliEvents::POST_BUILD_REPOSITORY, new BuildRepositoryEvent($this));
        }
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
        $this->mappingsByResource = new PathMappingCollection();
        $this->conflicts = new ConflictCollection();

        // Load mappings
        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getPathMappings() as $mapping) {
                $this->loadPathMapping($mapping, $package)->execute();
            }
        }

        // Scan all paths for conflicts
        $this->updateConflicts($this->mappingsByResource->getRepositoryPaths())->execute();
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
        return new LoadPathMapping($mapping, $package, $this->packages, $this->mappings, $this->mappingsByResource, $this->conflictDetector);
    }

    private function unloadPathMapping(PathMapping $mapping)
    {
        return new UnloadPathMapping($mapping, $this->packages, $this->mappings, $this->mappingsByResource, $this->conflictDetector);
    }

    private function syncRepositoryPath($repositoryPath)
    {
        return new SyncRepositoryPath($repositoryPath, $this->repo, $this->mappingsByResource, $this->overrideGraph);
    }

    private function populateRepository()
    {
        return new PopulateRepository($this->repo, $this->mappings, $this->overrideGraph);
    }

    private function updateConflicts(array $repositoryPaths = array())
    {
        return new UpdateConflicts($repositoryPaths, $this->conflictDetector, $this->conflicts, $this->mappingsByResource);
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
