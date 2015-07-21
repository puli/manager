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
use LogicException;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Conflict\OverrideGraph;
use Puli\Manager\Transaction\AtomicOperation;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Webmozart\PathUtil\Path;

/**
 * Synchronizes all mappings for a repository path with the repository.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SyncRepositoryPath implements AtomicOperation
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var PathMappingCollection
     */
    private $mappingsByResource;

    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var string[][][]
     */
    private $enabledFilesystemPathsBefore;

    /**
     * @var string[][][]
     */
    private $enabledFilesystemPathsAfter;

    /**
     * @param string                $repositoryPath
     * @param EditableRepository    $repo
     * @param PathMappingCollection $mappingsByResource
     * @param OverrideGraph         $overrideGraph
     */
    public function __construct($repositoryPath, EditableRepository $repo, PathMappingCollection $mappingsByResource, OverrideGraph $overrideGraph)
    {
        $this->repositoryPath = $repositoryPath;
        $this->repo = $repo;
        $this->mappingsByResource = $mappingsByResource;
        $this->overrideGraph = $overrideGraph;
    }

    /**
     * Records which mappings are currently enabled for the repository path.
     */
    public function takeSnapshot()
    {
        $this->enabledFilesystemPathsBefore = $this->getEnabledFilesystemPaths($this->repositoryPath);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (null === $this->enabledFilesystemPathsBefore) {
            throw new LogicException('takeSnapshot() was not called');
        }

        $this->enabledFilesystemPathsAfter = $this->getEnabledFilesystemPaths($this->repositoryPath);

        $this->sync($this->enabledFilesystemPathsBefore, $this->enabledFilesystemPathsAfter);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->sync($this->enabledFilesystemPathsAfter, $this->enabledFilesystemPathsBefore);
    }

    private function sync(array $filesystemPathsBefore, array $filesystemPathsAfter)
    {
        if (!$filesystemPathsBefore && $filesystemPathsAfter) {
            $this->add($this->repositoryPath, $filesystemPathsAfter);
        } elseif ($filesystemPathsBefore && !$filesystemPathsAfter) {
            $this->remove($this->repositoryPath);
        } elseif ($filesystemPathsBefore && $filesystemPathsAfter) {
            $this->replace($filesystemPathsBefore, $filesystemPathsAfter);
        }
    }

    private function remove($repositoryPath)
    {
        $this->repo->remove($repositoryPath);
    }

    private function add($repositoryPath, array $filesystemPaths)
    {
        try {
            $this->addInOrder($filesystemPaths);
        } catch (Exception $e) {
            $this->repo->remove($repositoryPath);

            throw $e;
        }
    }

    private function addInOrder(array $filesystemPaths)
    {
        foreach ($filesystemPaths as $packageName => $filesystemPathsByRepoPath) {
            foreach ($filesystemPathsByRepoPath as $repoPath => $filesystemPaths) {
                foreach ($filesystemPaths as $filesystemPath) {
                    $this->repo->add($repoPath, $this->createResource($filesystemPath));
                }
            }
        }
    }

    private function replace(array $filesystemPathsBefore, array $filesystemPathsAfter)
    {
        $filesystemPathsAfterSuffix = $filesystemPathsAfter;
        $filesystemPathsAfterPrefix = array_splice($filesystemPathsAfterSuffix, 0, count($filesystemPathsBefore));

        if ($filesystemPathsBefore === $filesystemPathsAfterPrefix) {
            // Optimization: If the package names before are a prefix of the
            // package names after, we can simply add the mappings for the
            // remaining package names
            // Note: array_splice() already removed the prefix of $filesystemPathsAfterSuffix
            $this->addInOrder($filesystemPathsAfterSuffix);

            return;
        }

        $shortestRepositoryPath = $this->getShortestRepositoryPath($filesystemPathsBefore, $filesystemPathsAfter);

        // Remove the shortest repository path before adding
        $this->remove($shortestRepositoryPath);

        try {
            $this->add($shortestRepositoryPath, $filesystemPathsAfter);
        } catch (Exception $e) {
            try {
                // Try to restore the previous paths before failing
                $this->add($shortestRepositoryPath, $filesystemPathsBefore);
            } catch (Exception $e) {
                // We are already handling an exception
            }

            throw $e;
        }
    }

    private function createResource($filesystemPath)
    {
        return is_dir($filesystemPath)
            ? new DirectoryResource($filesystemPath)
            : new FileResource($filesystemPath);
    }

    private function getShortestRepositoryPath(array $filesystemPathsBefore, array $filesystemPathsAfter)
    {
        $repositoryPaths = array();

        foreach ($filesystemPathsBefore as $filesystemPathsByRepoPaths) {
            foreach ($filesystemPathsByRepoPaths as $repoPath => $filesystemPath) {
                $repositoryPaths[$repoPath] = true;
            }
        }

        foreach ($filesystemPathsAfter as $filesystemPathsByRepoPaths) {
            foreach ($filesystemPathsByRepoPaths as $repoPath => $filesystemPath) {
                $repositoryPaths[$repoPath] = true;
            }
        }

        ksort($repositoryPaths);
        reset($repositoryPaths);

        return key($repositoryPaths);
    }

    private function getEnabledFilesystemPaths($repositoryPath)
    {
        // Get a copy so that we can remove the entries that we processed
        // already
        $mappings = $this->mappingsByResource->toArray();

        $this->collectEnabledFilesystemPaths($repositoryPath, $mappings, $filesystemPaths);

        if (!$filesystemPaths) {
            return array();
        }

        // Sort primary keys (package names)
        $sortedNames = $this->overrideGraph->getSortedPackageNames(array_keys($filesystemPaths));
        $filesystemPaths = array_replace(array_flip($sortedNames), $filesystemPaths);

        // Sort secondary keys (repository paths)
        foreach ($filesystemPaths as $packageName => $pathsByPackage) {
            ksort($filesystemPaths[$packageName]);
        }

        return $filesystemPaths;
    }

    /**
     * @param string          $repositoryPath
     * @param PathMapping[][] $uncheckedMappings
     * @param string[][]      $filesystemPaths
     * @param string[][]      $filesystemPaths
     */
    private function collectEnabledFilesystemPaths($repositoryPath, array &$uncheckedMappings, array &$filesystemPaths = null, array &$processedPaths = null)
    {
        if (null === $filesystemPaths) {
            $filesystemPaths = array();
            $processedPaths = array();
        }

        // We had this one before
        if (isset($processedPaths[$repositoryPath])) {
            return;
        }

        $processedPaths[$repositoryPath] = true;

        foreach ($uncheckedMappings as $mappedPath => $mappingsByPackage) {
            // Check mappings for the passed repository path or any of its
            // nested paths
            if ($repositoryPath !== $mappedPath && !Path::isBasePath($repositoryPath, $mappedPath)) {
                continue;
            }

            foreach ($mappingsByPackage as $packageName => $mapping) {
                // Don't check the mapping again for this repository path
                unset($uncheckedMappings[$mappedPath][$packageName]);

                // The path of the mapping is not necessarily the current repository
                // path. The paths are different when checking the nested paths of
                // a mapping.
                $mappingPath = $mapping->getRepositoryPath();

                // We added the mapping before or it is not enabled
                if (isset($filesystemPaths[$packageName][$mappingPath]) || !$mapping->isEnabled()) {
                    continue;
                }

                if (!isset($filesystemPaths[$packageName])) {
                    $filesystemPaths[$packageName] = array();
                }

                $filesystemPaths[$packageName][$mappingPath] = $mapping->getFilesystemPaths();

                // Check all nested paths of the mapping for other mappings that
                // map to the same paths
                foreach ($mapping->listRepositoryPaths() as $nestedRepositoryPath) {
                    // Don't repeat the call for the current path
                    if ($nestedRepositoryPath !== $mappedPath) {
                        $this->collectEnabledFilesystemPaths($nestedRepositoryPath, $uncheckedMappings, $filesystemPaths, $processedPaths);
                    }
                }
            }
        }
    }
}
