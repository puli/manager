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

use Puli\RepositoryManager\Api\Repository\RepositoryPathConflict;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Conflict\PackageConflict;
use Puli\RepositoryManager\Conflict\PackageConflictDetector;
use Puli\RepositoryManager\Transaction\AtomicOperation;
use Webmozart\PathUtil\Path;

/**
 * Updates the resource path conflicts.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UpdateConflicts implements AtomicOperation
{
    /**
     * @var string[]
     */
    private $repositoryPaths;

    /**
     * @var PackageConflictDetector
     */
    private $conflictDetector;

    /**
     * @var ConflictCollection
     */
    private $conflicts;

    /**
     * @var ResourceMappingCollection
     */
    private $mappings;

    /**
     * @var string[]
     */
    private $addedConflicts = array();

    /**
     * @var ResourceMapping[]
     */
    private $removedConflicts = array();

    public function __construct(array $repositoryPaths, PackageConflictDetector $conflictDetector, ConflictCollection $conflicts, ResourceMappingCollection $mappings)
    {
        $this->repositoryPaths = $repositoryPaths;
        $this->conflictDetector = $conflictDetector;
        $this->conflicts = $conflicts;
        $this->mappings = $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->repositoryPaths) {
            // Check all paths if none are passed
            $this->repositoryPaths = $this->conflicts->getRepositoryPaths();
        }

        // Mark all as resolved
        foreach ($this->repositoryPaths as $repositoryPath) {
            if ($this->conflicts->has($repositoryPath)) {
                $conflict = $this->conflicts->get($repositoryPath);
                $this->conflicts->remove($repositoryPath);
                $this->removedConflicts[$repositoryPath] = $conflict->getMappings();
                $conflict->resolve();
            }
        }

        $packageConflicts = $this->conflictDetector->detectConflicts($this->repositoryPaths);

        $this->deduplicatePackageConflicts($packageConflicts);

        foreach ($packageConflicts as $packageConflict) {
            $repositoryPath = $packageConflict->getConflictingToken();
            $conflict = new RepositoryPathConflict($repositoryPath);

            foreach ($packageConflict->getPackageNames() as $packageName) {
                $conflict->addMapping($this->mappings->get($repositoryPath, $packageName));
            }

            $this->conflicts->add($conflict);
            $this->addedConflicts[] = $repositoryPath;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        foreach ($this->addedConflicts as $repositoryPath) {
            if ($this->conflicts->has($repositoryPath)) {
                $conflict = $this->conflicts->get($repositoryPath);
                $conflict->resolve();
                $this->conflicts->remove($repositoryPath);
            }
        }

        foreach ($this->removedConflicts as $repositoryPath => $conflictingMappings) {
            $conflict = new RepositoryPathConflict($repositoryPath);
            $conflict->addMappings($conflictingMappings);
            $this->conflicts->add($conflict);
        }
    }

    /**
     * @param PackageConflict[] $packageConflicts
     */
    private function deduplicatePackageConflicts(array &$packageConflicts)
    {
        $indicesByPath = array();
        $indicesToRemove = array();

        foreach ($packageConflicts as $index => $packageConflict) {
            $indicesByPath[$packageConflict->getConflictingToken()] = $index;
        }

        foreach ($indicesByPath as $repositoryPath => $index) {
            foreach ($indicesByPath as $otherPath => $otherIndex) {
                if ($otherPath !== $repositoryPath && Path::isBasePath($otherPath, $repositoryPath)) {
                    $indicesToRemove[$index] = true;
                }
            }
        }

        foreach ($indicesToRemove as $index => $true) {
            unset($packageConflicts[$index]);
        }

        // Reorganize indices
        $packageConflicts = array_values($packageConflicts);
    }
}
