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
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\PackageConflictDetector;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Loads a path mapping.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoadPathMapping implements AtomicOperation
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

    public function __construct(PathMapping $mapping, Package $containingPackage, PackageCollection $packages, PathMappingCollection $mappings, PathMappingCollection $mappingsByResource, PackageConflictDetector $conflictDetector)
    {
        $this->mapping = $mapping;
        $this->containingPackage = $containingPackage;
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
        if ($this->mapping->isLoaded()) {
            return;
        }

        $this->mapping->load($this->containingPackage, $this->packages);

        $packageName = $this->containingPackage->getName();

        $this->mappings->add($this->mapping);

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappingsByResource->set($repositoryPath, $this->mapping);
            $this->conflictDetector->claim($repositoryPath, $packageName);
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

        $packageName = $this->containingPackage->getName();

        $this->mappings->remove($this->mapping->getRepositoryPath(), $packageName);

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappingsByResource->remove($repositoryPath, $packageName);
            $this->conflictDetector->release($repositoryPath, $packageName);
        }

        // Unload after iterating, otherwise the paths are gone
        $this->mapping->unload();
    }
}
