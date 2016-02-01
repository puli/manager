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

use Exception;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\DependencyGraph;
use Puli\Manager\Transaction\AtomicOperation;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;

/**
 * Inserts all path mappings into the repository.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PopulateRepository implements AtomicOperation
{
    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var PathMappingCollection
     */
    private $mappings;

    /**
     * @var DependencyGraph
     */
    private $overrideGraph;

    /**
     * @var bool
     */
    private $added = false;

    public function __construct(EditableRepository $repo, PathMappingCollection $mappings, DependencyGraph $overrideGraph)
    {
        $this->repo = $repo;
        $this->mappings = $mappings;
        $this->overrideGraph = $overrideGraph;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // Quit if no mappings exist
        if (!$moduleNames = $this->mappings->getModuleNames()) {
            return;
        }

        $sortedNames = $this->overrideGraph->getSortedModuleNames($moduleNames);

        try {
            foreach ($sortedNames as $moduleName) {
                foreach ($this->getEnabledMappingsByModuleName($moduleName) as $repositoryPath => $mapping) {
                    foreach ($mapping->getFilesystemPaths() as $filesystemPath) {
                        $this->repo->add($repositoryPath, $this->createResource($filesystemPath));
                        $this->added = true;
                    }
                }
            }
        } catch (Exception $e) {
            $this->repo->clear();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->added) {
            $this->repo->clear();
        }
    }

    /**
     * @param string $moduleName
     *
     * @return PathMapping[]
     */
    private function getEnabledMappingsByModuleName($moduleName)
    {
        $mappingsToAdd = array();

        foreach ($this->mappings->listByModuleName($moduleName) as $repositoryPath => $mapping) {
            if ($mapping->isEnabled()) {
                // Remove duplicates
                $mappingsToAdd[$mapping->getRepositoryPath()] = $mapping;
            }
        }

        return $mappingsToAdd;
    }

    private function createResource($filesystemPath)
    {
        return is_dir($filesystemPath)
            ? new DirectoryResource($filesystemPath)
            : new FileResource($filesystemPath);
    }
}
