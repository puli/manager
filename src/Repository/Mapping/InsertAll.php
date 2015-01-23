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

use Exception;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\RepositoryManager\Api\Repository\RepositoryNotEmptyException;
use Puli\RepositoryManager\Api\Repository\ResourceMapping;
use Puli\RepositoryManager\Conflict\OverrideGraph;
use Puli\RepositoryManager\Transaction\AtomicOperation;

/**
 * Inserts all resource mappings into the repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InsertAll implements AtomicOperation
{
    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var ResourceMappingCollection
     */
    private $mappings;

    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var bool
     */
    private $added = false;

    public function __construct(EditableRepository $repo, ResourceMappingCollection $mappings, OverrideGraph $overrideGraph)
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
        if ($this->repo->hasChildren('/')) {
            throw new RepositoryNotEmptyException('The repository is not empty.');
        }

        // Quit if no mappings exist
        if (!$packageNames = $this->mappings->getPackageNames()) {
            return;
        }

        $sortedNames = $this->overrideGraph->getSortedPackageNames($packageNames);

        try {
            foreach ($sortedNames as $packageName) {
                foreach ($this->getEnabledMappingsByPackageName($packageName) as $repositoryPath => $mapping) {
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
     * @param string $packageName
     *
     * @return ResourceMapping[]
     */
    private function getEnabledMappingsByPackageName($packageName)
    {
        $mappingsToAdd = array();

        foreach ($this->mappings->listByPackageName($packageName) as $repositoryPath => $mapping) {
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
