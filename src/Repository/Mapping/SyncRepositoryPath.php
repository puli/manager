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
            $this->addInOrder($filesystemPaths, $this->overrideGraph->getSortedPackageNames());
        } catch (Exception $e) {
            $this->repo->remove($repositoryPath);

            throw $e;
        }
    }

    private function replace(array $filesystemPathsBefore, array $filesystemPathsAfter)
    {
        $packageNamesBefore = $this->getSortedPackageNames($filesystemPathsBefore);
        $packageNamesAfter = $this->getSortedPackageNames($filesystemPathsAfter);

        $packageNamesAfterPrefix = array_splice($packageNamesAfter, 0, count($packageNamesBefore));

        if ($packageNamesBefore === $packageNamesAfterPrefix) {
            // Optimization: If the package names before are a prefix of the
            // package names after, we can simply add the mappings for the
            // remaining package names
            // Note: array_splice() already removed the prefix of $packageNamesAfter
            $this->addInOrder($filesystemPathsAfter, $packageNamesAfter);

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

    private function addInOrder(array $filesystemPaths, array $sortedPackageNames)
    {
        foreach ($filesystemPaths as $nestedRepoPath => $filesystemPathsByPackage) {
            foreach ($sortedPackageNames as $packageName) {
                if (!isset($filesystemPathsByPackage[$packageName])) {
                    continue;
                }

                foreach ($filesystemPathsByPackage[$packageName] as $filesystemPath) {
                    $this->repo->add($nestedRepoPath, $this->createResource($filesystemPath));
                }
            }
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
        $repositoryPaths = array_keys(array_replace($filesystemPathsBefore, $filesystemPathsAfter));

        sort($repositoryPaths);

        return reset($repositoryPaths);
    }

    private function getSortedPackageNames(array $filesystemPaths)
    {
        $packageNames = array();

        foreach ($filesystemPaths as $pathsByPackageName) {
            $packageNames = array_merge($packageNames, array_keys($pathsByPackageName));
        }

        // Duplicates are removed here
        return $this->overrideGraph->getSortedPackageNames($packageNames);
    }

    private function getEnabledFilesystemPaths($repositoryPath)
    {
        // Get a copy so that we can remove the entries that we processed
        // already
        $mappings = $this->mappingsByResource->toArray();

        $this->collectEnabledFilesystemPaths($repositoryPath, $mappings, $filesystemPaths);

        ksort($filesystemPaths);

        return $filesystemPaths;
    }

    /**
     * @param string          $repositoryPath
     * @param PathMapping[][] $uncheckedMappings
     * @param string[][]      $filesystemPaths
     */
    private function collectEnabledFilesystemPaths($repositoryPath, array &$uncheckedMappings, array &$filesystemPaths = null)
    {
        if (null === $filesystemPaths) {
            $filesystemPaths = array();
        }

        // We had this one before
        if (isset($filesystemPaths[$repositoryPath])) {
            return;
        }

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
                if (isset($filesystemPaths[$mappingPath][$packageName]) || !$mapping->isEnabled()) {
                    continue;
                }

                if (!isset($filesystemPaths[$mappingPath])) {
                    $filesystemPaths[$mappingPath] = array();
                }

                $filesystemPaths[$mappingPath][$packageName] = $mapping->getFilesystemPaths();

                // Check all nested paths of the mapping for other mappings that
                // map to the same paths
                foreach ($mapping->listRepositoryPaths() as $nestedRepositoryPath) {
                    // Don't repeat the call for the current path
                    if ($nestedRepositoryPath !== $mappedPath) {
                        $this->collectEnabledFilesystemPaths($nestedRepositoryPath,
                            $uncheckedMappings, $filesystemPaths);
                    }
                }
            }
        }
    }
}
