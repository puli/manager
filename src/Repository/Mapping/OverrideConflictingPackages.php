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

use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Repository\ResourceMapping;
use Puli\Manager\Conflict\OverrideGraph;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Adds an override statement for each package conflicting with the root package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OverrideConflictingPackages implements AtomicOperation
{
    /**
     * @var ResourceMapping
     */
    private $mapping;

    /**
     * @var RootPackage
     */
    private $rootPackage;

    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var string[]
     */
    private $overriddenPackages = array();

    /**
     * @var string[]
     */
    private $addedEdgesFrom = array();

    public function __construct(ResourceMapping $mapping, RootPackage $rootPackage, OverrideGraph $overrideGraph)
    {
        $this->mapping = $mapping;
        $this->rootPackage = $rootPackage;
        $this->overrideGraph = $overrideGraph;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $rootPackageName = $this->rootPackage->getName();
        $rootPackageFile = $this->rootPackage->getPackageFile();

        foreach ($this->mapping->getConflictingPackages() as $conflictingPackage) {
            $packageName = $conflictingPackage->getName();

            if (!$rootPackageFile->hasOverriddenPackage($packageName)) {
                $rootPackageFile->addOverriddenPackage($packageName);
                $this->overriddenPackages[] = $packageName;
            }

            if (!$this->overrideGraph->hasEdge($packageName, $rootPackageName)) {
                $this->overrideGraph->addEdge($packageName, $rootPackageName);
                $this->addedEdgesFrom[] = $packageName;

            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $rootPackageName = $this->rootPackage->getName();
        $rootPackageFile = $this->rootPackage->getPackageFile();

        foreach ($this->overriddenPackages as $packageName) {
            $rootPackageFile->removeOverriddenPackage($packageName);
        }

        foreach ($this->addedEdgesFrom as $packageName) {
            $this->overrideGraph->removeEdge($packageName, $rootPackageName);
        }
    }
}
