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
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Event\BuildRepositoryEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleCollection;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Repository\DuplicatePathMappingException;
use Puli\Manager\Api\Repository\NoSuchPathMappingException;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Conflict\ModuleConflictDetector;
use Puli\Manager\Conflict\OverrideGraph;
use Puli\Manager\Module\ModuleFileStorage;
use Puli\Manager\Repository\Mapping\AddPathMappingToModuleFile;
use Puli\Manager\Repository\Mapping\ConflictCollection;
use Puli\Manager\Repository\Mapping\LoadPathMapping;
use Puli\Manager\Repository\Mapping\OverrideConflictingModules;
use Puli\Manager\Repository\Mapping\PathMappingCollection;
use Puli\Manager\Repository\Mapping\PopulateRepository;
use Puli\Manager\Repository\Mapping\RemovePathMappingFromModuleFile;
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
     * @var ProjectContext
     */
    private $context;

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
     * @var RootModule
     */
    private $rootModule;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var ModuleCollection
     */
    private $modules;

    /**
     * @var ModuleFileStorage
     */
    private $moduleFileStorage;

    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var ModuleConflictDetector
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
     * @param ProjectContext     $context
     * @param EditableRepository $repo
     * @param ModuleCollection   $modules
     * @param ModuleFileStorage  $moduleFileStorage
     */
    public function __construct(ProjectContext $context, EditableRepository $repo, ModuleCollection $modules, ModuleFileStorage $moduleFileStorage)
    {
        $this->context = $context;
        $this->dispatcher = $context->getEventDispatcher();
        $this->repo = $repo;
        $this->config = $context->getConfig();
        $this->rootDir = $context->getRootDirectory();
        $this->rootModule = $modules->getRootModule();
        $this->rootModuleFile = $context->getRootModuleFile();
        $this->modules = $modules;
        $this->moduleFileStorage = $moduleFileStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
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

        if (!($flags & self::OVERRIDE) && $this->rootModuleFile->hasPathMapping($mapping->getRepositoryPath())) {
            throw DuplicatePathMappingException::forRepositoryPath($mapping->getRepositoryPath(), $this->rootModule->getName());
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncRepositoryPath($mapping->getRepositoryPath());
            $syncOp->takeSnapshot();

            $tx->execute($this->loadPathMapping($mapping, $this->rootModule));

            if (!($flags & self::IGNORE_FILE_NOT_FOUND)) {
                $this->assertNoLoadErrors($mapping);
            }

            $tx->execute($this->updateConflicts($mapping->listRepositoryPaths()));
            $tx->execute($this->overrideConflictingModules($mapping));
            $tx->execute($this->updateConflicts());
            $tx->execute($this->addPathMappingToModuleFile($mapping));
            $tx->execute($syncOp);

            $this->saveRootModuleFile();

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

        if (!$this->mappings->contains($repositoryPath, $this->rootModule->getName())) {
            return;
        }

        $mapping = $this->mappings->get($repositoryPath, $this->rootModule->getName());
        $tx = new Transaction();

        try {
            $syncOp = $this->syncRepositoryPath($repositoryPath);
            $syncOp->takeSnapshot();

            $tx->execute($this->unloadPathMapping($mapping));
            $tx->execute($this->removePathMappingFromModuleFile($repositoryPath));
            $tx->execute($syncOp);

            $this->saveRootModuleFile();

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
                if ($expr->evaluate($mapping)) {
                    $syncOp = $this->syncRepositoryPath($mapping->getRepositoryPath());
                    $syncOp->takeSnapshot();

                    $tx->execute($this->unloadPathMapping($mapping));
                    $tx->execute($this->removePathMappingFromModuleFile($mapping->getRepositoryPath()));
                    $tx->execute($syncOp);
                }
            }

            $this->saveRootModuleFile();

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
        return $this->getPathMapping($repositoryPath, $this->rootModule->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function findRootPathMappings(Expression $expr)
    {
        $expr = Expr::method('getContainingModule', Expr::same($this->rootModule))
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
        $rootModuleName = $this->rootModule->getName();

        foreach ($this->mappings->toArray() as $mappingsByModule) {
            if (isset($mappingsByModule[$rootModuleName])) {
                $mappings[] = $mappingsByModule[$rootModuleName];
            }
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootPathMapping($repositoryPath)
    {
        return $this->hasPathMapping($repositoryPath, $this->rootModule->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootPathMappings(Expression $expr = null)
    {
        $expr2 = Expr::method('getContainingModule', Expr::same($this->rootModule));

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasPathMappings($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMapping($repositoryPath, $moduleName)
    {
        Assert::string($repositoryPath, 'The repository path must be a string. Got: %s');
        Assert::string($moduleName, 'The module name must be a string. Got: %s');

        $this->assertMappingsLoaded();

        if (!$this->mappings->contains($repositoryPath, $moduleName)) {
            throw NoSuchPathMappingException::forRepositoryPathAndModule($repositoryPath, $moduleName);
        }

        return $this->mappings->get($repositoryPath, $moduleName);
    }

    /**
     * {@inheritdoc}
     */
    public function getPathMappings()
    {
        $this->assertMappingsLoaded();

        $mappings = array();

        foreach ($this->mappings->toArray() as $mappingsByModule) {
            foreach ($mappingsByModule as $mapping) {
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

        foreach ($this->mappings->toArray() as $mappingsByModule) {
            foreach ($mappingsByModule as $mapping) {
                if ($expr->evaluate($mapping)) {
                    $mappings[] = $mapping;
                }
            }
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPathMapping($repositoryPath, $moduleName)
    {
        Assert::string($repositoryPath, 'The repository path must be a string. Got: %s');
        Assert::string($moduleName, 'The module name must be a string. Got: %s');

        $this->assertMappingsLoaded();

        return $this->mappings->contains($repositoryPath, $moduleName);
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

        foreach ($this->mappings->toArray() as $mappingsByModule) {
            foreach ($mappingsByModule as $mapping) {
                if ($expr->evaluate($mapping)) {
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
        $this->overrideGraph = OverrideGraph::forModules($this->modules);
        $this->conflictDetector = new ModuleConflictDetector($this->overrideGraph);
        $this->mappings = new PathMappingCollection();
        $this->mappingsByResource = new PathMappingCollection();
        $this->conflicts = new ConflictCollection();

        // Load mappings
        foreach ($this->modules as $module) {
            if (null === $module->getModuleFile()) {
                continue;
            }

            foreach ($module->getModuleFile()->getPathMappings() as $mapping) {
                $this->loadPathMapping($mapping, $module)->execute();
            }
        }

        // Scan all paths for conflicts
        $this->updateConflicts($this->mappingsByResource->getRepositoryPaths())->execute();
    }

    private function addPathMappingToModuleFile(PathMapping $mapping)
    {
        return new AddPathMappingToModuleFile($mapping, $this->rootModuleFile);
    }

    private function removePathMappingFromModuleFile($repositoryPath)
    {
        return new RemovePathMappingFromModuleFile($repositoryPath, $this->rootModuleFile);
    }

    private function loadPathMapping(PathMapping $mapping, Module $module)
    {
        return new LoadPathMapping($mapping, $module, $this->modules, $this->mappings, $this->mappingsByResource, $this->conflictDetector);
    }

    private function unloadPathMapping(PathMapping $mapping)
    {
        return new UnloadPathMapping($mapping, $this->modules, $this->mappings, $this->mappingsByResource, $this->conflictDetector);
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
        return new UpdateConflicts($repositoryPaths, $this->conflictDetector, $this->conflicts, $this->mappingsByResource);
    }

    private function overrideConflictingModules(PathMapping $mapping)
    {
        return new OverrideConflictingModules($mapping, $this->rootModule, $this->overrideGraph);
    }

    private function saveRootModuleFile()
    {
        $this->moduleFileStorage->saveRootModuleFile($this->rootModuleFile);
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
