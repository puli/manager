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
use Puli\Manager\Api\Module\ModuleCollection;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\ModuleConflictDetector;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Loads a path mapping.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoadPathMapping implements AtomicOperation
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
     * @var ModuleCollection
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

    public function __construct(PathMapping $mapping, Module $containingModule, ModuleCollection $modules, PathMappingCollection $mappings, PathMappingCollection $mappingsByResource, ModuleConflictDetector $conflictDetector)
    {
        $this->mapping = $mapping;
        $this->containingModule = $containingModule;
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
        if ($this->mapping->isLoaded()) {
            return;
        }

        $this->mapping->load($this->containingModule, $this->modules);

        $moduleName = $this->containingModule->getName();

        $this->mappings->add($this->mapping);

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappingsByResource->set($repositoryPath, $this->mapping);
            $this->conflictDetector->claim($repositoryPath, $moduleName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if (!$this->mapping->isLoaded()) {
            return;
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
}
