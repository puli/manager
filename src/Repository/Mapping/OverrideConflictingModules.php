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

use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\OverrideGraph;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Adds an override statement for each module conflicting with the root module.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OverrideConflictingModules implements AtomicOperation
{
    /**
     * @var PathMapping
     */
    private $mapping;

    /**
     * @var RootModule
     */
    private $rootModule;

    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var string[]
     */
    private $overriddenModules = array();

    /**
     * @var string[]
     */
    private $addedEdgesFrom = array();

    public function __construct(PathMapping $mapping, RootModule $rootModule, OverrideGraph $overrideGraph)
    {
        $this->mapping = $mapping;
        $this->rootModule = $rootModule;
        $this->overrideGraph = $overrideGraph;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $rootModuleName = $this->rootModule->getName();
        $rootModuleFile = $this->rootModule->getModuleFile();

        foreach ($this->mapping->getConflictingModules() as $conflictingModule) {
            $moduleName = $conflictingModule->getName();

            if (!$rootModuleFile->hasOverriddenModule($moduleName)) {
                $rootModuleFile->addOverriddenModule($moduleName);
                $this->overriddenModules[] = $moduleName;
            }

            if (!$this->overrideGraph->hasEdge($moduleName, $rootModuleName)) {
                $this->overrideGraph->addEdge($moduleName, $rootModuleName);
                $this->addedEdgesFrom[] = $moduleName;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $rootModuleName = $this->rootModule->getName();
        $rootModuleFile = $this->rootModule->getModuleFile();

        foreach ($this->overriddenModules as $moduleName) {
            $rootModuleFile->removeOverriddenModule($moduleName);
        }

        foreach ($this->addedEdgesFrom as $moduleName) {
            $this->overrideGraph->removeEdge($moduleName, $rootModuleName);
        }
    }
}
