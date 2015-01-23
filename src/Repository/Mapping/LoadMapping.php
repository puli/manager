<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository\Mapping;

use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Conflict\PackageConflictDetector;
use Puli\RepositoryManager\Transaction\AtomicOperation;

/**
 * Loads a resource mapping.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoadMapping implements AtomicOperation
{
    /**
     * @var ResourceMapping
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
     * @var ResourceMappingCollection
     */
    private $mappings;

    /**
     * @var PackageConflictDetector
     */
    private $conflictDetector;

    public function __construct(ResourceMapping $mapping, Package $containingPackage, PackageCollection $packages, ResourceMappingCollection $mappings, PackageConflictDetector $conflictDetector)
    {
        $this->mapping = $mapping;
        $this->containingPackage = $containingPackage;
        $this->packages = $packages;
        $this->mappings = $mappings;
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

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappings->set($repositoryPath, $this->mapping);
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

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappings->remove($repositoryPath, $packageName);
            $this->conflictDetector->release($repositoryPath, $packageName);
        }

        // Unload after iterating, otherwise the paths are gone
        $this->mapping->unload();
    }
}
