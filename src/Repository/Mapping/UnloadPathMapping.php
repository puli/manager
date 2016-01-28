<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Repository\Mapping;

use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Repository\PathConflict;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\ModuleConflictDetector;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Unloads a path mapping.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnloadPathMapping implements AtomicOperation
{
    /**
     * @var PathMapping
     */
    private $mapping;

    /**
     * @var Module
     */
    private $containingModule;

    /**
     * @var ModuleList
     */
    private $modules;

    /**
     * @var PathMappingCollection
     */
    private $mappings;

    /**
     * @var PathMappingCollection
     */
    private $mappingsByResource;

    /**
     * @var ModuleConflictDetector
     */
    private $conflictDetector;

    /**
     * @var PathConflict[]
     */
    private $conflicts = array();

    /**
     * @var PathMapping[][]
     */
    private $conflictingMappings = array();

    public function __construct(PathMapping $mapping, ModuleList $modules, PathMappingCollection $mappings, PathMappingCollection $mappingsByResource, ModuleConflictDetector $conflictDetector)
    {
        $this->mapping = $mapping;
        $this->modules = $modules;
        $this->mappings = $mappings;
        $this->mappingsByResource = $mappingsByResource;
        $this->conflictDetector = $conflictDetector;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->mapping->isLoaded()) {
            return;
        }

        $this->containingModule = $this->mapping->getContainingModule();

        // Remember the conflicts that will be adjusted during unload()
        foreach ($this->mapping->getConflicts() as $conflict) {
            $this->conflicts[$conflict->getRepositoryPath()] = $conflict;
            $this->conflictingMappings[$conflict->getRepositoryPath()] = $conflict->getMappings();
        }

        $moduleName = $this->containingModule->getName();

        $this->mappings->remove($this->mapping->getRepositoryPath(), $moduleName);

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappingsByResource->remove($repositoryPath, $moduleName);
            $this->conflictDetector->release($repositoryPath, $moduleName);
        }

        // Unload after iterating, otherwise the paths are gone
        $this->mapping->unload();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->mapping->isLoaded() || !$this->containingModule) {
            return;
        }

        $this->mapping->load($this->containingModule, $this->modules);

        $moduleName = $this->containingModule->getName();

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappings->add($this->mapping);
            $this->conflictDetector->claim($repositoryPath, $moduleName);
        }

        // Restore conflicts
        foreach ($this->conflicts as $repositoryPath => $conflict) {
            $conflict->addMappings($this->conflictingMappings[$repositoryPath]);
        }
    }
}
