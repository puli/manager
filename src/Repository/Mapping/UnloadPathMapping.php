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

use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Repository\PathConflict;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\PackageConflictDetector;
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
     * @var Package
     */
    private $containingPackage;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PathMappingCollection
     */
    private $mappings;

    /**
     * @var PathMappingCollection
     */
    private $mappingsByResource;

    /**
     * @var PackageConflictDetector
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

    public function __construct(PathMapping $mapping, PackageCollection $packages, PathMappingCollection $mappings, PathMappingCollection $mappingsByResource, PackageConflictDetector $conflictDetector)
    {
        $this->mapping = $mapping;
        $this->packages = $packages;
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

        $this->containingPackage = $this->mapping->getContainingPackage();

        // Remember the conflicts that will be adjusted during unload()
        foreach ($this->mapping->getConflicts() as $conflict) {
            $this->conflicts[$conflict->getRepositoryPath()] = $conflict;
            $this->conflictingMappings[$conflict->getRepositoryPath()] = $conflict->getMappings();
        }

        $packageName = $this->containingPackage->getName();

        $this->mappings->remove($this->mapping->getRepositoryPath(), $packageName);

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappingsByResource->remove($repositoryPath, $packageName);
            $this->conflictDetector->release($repositoryPath, $packageName);
        }

        // Unload after iterating, otherwise the paths are gone
        $this->mapping->unload();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->mapping->isLoaded() || !$this->containingPackage) {
            return;
        }

        $this->mapping->load($this->containingPackage, $this->packages);

        $packageName = $this->containingPackage->getName();

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappings->add($this->mapping);
            $this->conflictDetector->claim($repositoryPath, $packageName);
        }

        // Restore conflicts
        foreach ($this->conflicts as $repositoryPath => $conflict) {
            $conflict->addMappings($this->conflictingMappings[$repositoryPath]);
        }
    }
}
