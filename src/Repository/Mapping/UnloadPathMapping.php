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
 * Unloads a path mapping.
 *
 * @since  1.0
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
     * @var PackageConflictDetector
     */
    private $conflictDetector;

    public function __construct(PathMapping $mapping, PackageCollection $packages, PathMappingCollection $mappings, PackageConflictDetector $conflictDetector)
    {
        $this->mapping = $mapping;
        $this->packages = $packages;
        $this->mappings = $mappings;
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

        $packageName = $this->containingPackage->getName();

        foreach ($this->mapping->listRepositoryPaths() as $repositoryPath) {
            $this->mappings->remove($repositoryPath, $packageName);
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
    }
}
