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
use Puli\Manager\Conflict\DependencyGraph;
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
    private $mappings;

    /**
     * @var DependencyGraph
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
     * @param PathMappingCollection $mappings
     * @param DependencyGraph       $overrideGraph
     */
    public function __construct($repositoryPath, EditableRepository $repo, PathMappingCollection $mappings, DependencyGraph $overrideGraph)
    {
        $this->repositoryPath = $repositoryPath;
        $this->repo = $repo;
        $this->mappings = $mappings;
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
        foreach ($filesystemPaths as $moduleName => $filesystemPathsByRepoPath) {
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
            // Optimization: If the module names before are a prefix of the
            // module names after, we can simply add the mappings for the
            // remaining module names
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
        $inMappings = $this->mappings->toArray();
        $outMappings = array();
        $filesystemPaths = array();

        $this->filterEnabledMappings($repositoryPath, $inMappings, $outMappings);

        foreach ($outMappings as $mappingPath => $mappingsByModule) {
            foreach ($mappingsByModule as $moduleName => $mapping) {
                $filesystemPaths[$moduleName][$mappingPath] = $mapping->getFilesystemPaths();
            }
        }

        if (!$filesystemPaths) {
            return array();
        }

        // Sort primary keys (module names)
        $sortedNames = $this->overrideGraph->getSortedModuleNames(array_keys($filesystemPaths));
        $filesystemPaths = array_replace(array_flip($sortedNames), $filesystemPaths);

        // Sort secondary keys (repository paths)
        foreach ($filesystemPaths as $moduleName => $pathsByModule) {
            ksort($filesystemPaths[$moduleName]);
        }

        return $filesystemPaths;
    }

    /**
     * @param string          $repositoryPath
     * @param PathMapping[][] $inMappings
     * @param PathMapping[][] $outMappings
     * @param bool[]          $processedPaths
     */
    private function filterEnabledMappings($repositoryPath, array &$inMappings, array &$outMappings, array &$processedPaths = array())
    {
        $repositoryPaths = array();
        $processedPaths[$repositoryPath] = true;

        foreach ($inMappings as $mappingPath => $mappingsByModule) {
            foreach ($mappingsByModule as $moduleName => $mapping) {
                if (!$mapping->isEnabled()) {
                    continue;
                }

                $nestedMappingPaths = $mapping->listRepositoryPaths();

                // Check that the mapping actually contains the path
                if (!Path::isBasePath($repositoryPath, $mappingPath) && !in_array($repositoryPath, $nestedMappingPaths, true)) {
                    continue;
                }

                // Don't check this mapping anymore in recursive calls
                unset($inMappings[$mappingPath][$moduleName]);

                if (empty($inMappings[$mappingPath])) {
                    unset($inMappings[$mappingPath]);
                }

                // Add mapping to output
                $outMappings[$mappingPath][$moduleName] = $mapping;

                foreach ($nestedMappingPaths as $nestedMappingPath) {
                    $repositoryPaths[$nestedMappingPath] = true;
                }
            }
        }

        // Continue to search for mappings for the repository paths we collected
        // already until there are no more mappings
        if (!empty($inMappings)) {
            foreach ($repositoryPaths as $nestedMappingPath => $true) {
                // Don't process paths twice
                if (!isset($processedPaths[$nestedMappingPath])) {
                    $this->filterEnabledMappings($nestedMappingPath, $inMappings, $outMappings, $processedPaths);
                }
            }
        }
    }
}
