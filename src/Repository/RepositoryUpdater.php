<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\RepositoryManager\Package\Graph\PackageNameGraph;

/**
 * Adds new resources to a resource repository.
 *
 * You can register resource mappings for insertion by calling {@link add()}.
 * All registered mappings will be inserted into the repository when you
 * call {@link commit()}. This method respects the package order specified
 * in the package graph passed to the constructor.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryUpdater
{
    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var PackageNameGraph
     */
    private $packageGraph;

    /**
     * @var string[][][]
     */
    private $additions = array();

    /**
     * Creates a new updater.
     *
     * @param EditableRepository $repo         The repository to update.
     * @param PackageNameGraph   $packageGraph The package graph determining the
     *                                         order in which to process the
     *                                         packages.
     */
    public function __construct(EditableRepository $repo, PackageNameGraph $packageGraph)
    {
        $this->repo = $repo;
        $this->packageGraph = $packageGraph;
    }

    /**
     * Adds a resource mapping to be inserted for a given package.
     *
     * The filesystem paths of the mapping need to have been converted to
     * absolute paths.
     *
     * @param ResourceMapping $mapping     The resource mapping.
     * @param string          $packageName The package name that defines the
     *                                     mapping.
     */
    public function add(ResourceMapping $mapping, $packageName)
    {
        if (!isset($this->additions[$packageName])) {
            $this->additions[$packageName] = array();
        }

        $this->additions[$packageName][$mapping->getRepositoryPath()] = $mapping->getFilesystemPaths();
    }

    /**
     * Clears all pending additions.
     */
    public function clear()
    {
        $this->additions = array();
    }

    /**
     * Adds all pending additions to the repository.
     *
     * The resources are added to the repository in the order determined by the
     * package graph passed to the constructor. Resources of overridden
     * packages are added to the repository before the resources of the
     * overriding packages.
     */
    public function commit()
    {
        if (!$this->additions) {
            return;
        }

        $packageNames = array_keys($this->additions);
        $sortedNames = $this->packageGraph->getSortedPackageNames($packageNames);

        foreach ($sortedNames as $packageName) {
            foreach ($this->additions[$packageName] as $repositoryPath => $filesystemPaths) {
                foreach ($filesystemPaths as $filesystemPath) {
                    $resource = is_dir($filesystemPath)
                        ? new DirectoryResource($filesystemPath)
                        : new FileResource($filesystemPath);

                    $this->repo->add($repositoryPath, $resource);
                }

                unset($this->additions[$packageName][$repositoryPath]);
            }
        }
    }
}
