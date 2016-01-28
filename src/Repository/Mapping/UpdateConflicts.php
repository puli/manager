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

use Puli\Manager\Api\Repository\PathConflict;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\ModuleConflict;
use Puli\Manager\Conflict\ModuleConflictDetector;
use Puli\Manager\Transaction\AtomicOperation;
use Webmozart\PathUtil\Path;

/**
 * Updates the resource path conflicts.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UpdateConflicts implements AtomicOperation
{
    /**
     * @var string[]
     */
    private $repositoryPaths;

    /**
     * @var ModuleConflictDetector
     */
    private $conflictDetector;

    /**
     * @var ConflictCollection
     */
    private $conflicts;

    /**
     * @var PathMappingCollection
     */
    private $mappingsByResource;

    /**
     * @var string[]
     */
    private $addedConflicts = array();

    /**
     * @var PathMapping[][]
     */
    private $removedConflicts = array();

    public function __construct(array $repositoryPaths, ModuleConflictDetector $conflictDetector, ConflictCollection $conflicts, PathMappingCollection $mappingsByResource)
    {
        $this->repositoryPaths = $repositoryPaths;
        $this->conflictDetector = $conflictDetector;
        $this->conflicts = $conflicts;
        $this->mappingsByResource = $mappingsByResource;
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

        $moduleConflicts = $this->conflictDetector->detectConflicts($this->repositoryPaths);

        $this->deduplicateModuleConflicts($moduleConflicts);

        foreach ($moduleConflicts as $moduleConflict) {
            $repositoryPath = $moduleConflict->getConflictingToken();
            $conflict = new PathConflict($repositoryPath);

            foreach ($moduleConflict->getModuleNames() as $moduleName) {
                $conflict->addMapping($this->mappingsByResource->get($repositoryPath, $moduleName));
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
            $conflict = new PathConflict($repositoryPath);
            $conflict->addMappings($conflictingMappings);
            $this->conflicts->add($conflict);
        }
    }

    /**
     * @param ModuleConflict[] $moduleConflicts
     */
    private function deduplicateModuleConflicts(array &$moduleConflicts)
    {
        $indicesByPath = array();
        $indicesToRemove = array();

        foreach ($moduleConflicts as $index => $moduleConflict) {
            $indicesByPath[$moduleConflict->getConflictingToken()] = $index;
        }

        foreach ($indicesByPath as $repositoryPath => $index) {
            foreach ($indicesByPath as $otherPath => $otherIndex) {
                if ($otherPath !== $repositoryPath && Path::isBasePath($otherPath, $repositoryPath)) {
                    $indicesToRemove[$index] = true;
                }
            }
        }

        foreach ($indicesToRemove as $index => $true) {
            unset($moduleConflicts[$index]);
        }

        // Reorganize indices
        $moduleConflicts = array_values($moduleConflicts);
    }
}
