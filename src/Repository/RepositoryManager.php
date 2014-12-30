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
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Webmozart\PathUtil\Path;

/**
 * Manages the resource repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var EditableRepository
     */
    private $repo;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * Creates a repository manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     */
    public function __construct(ProjectEnvironment $environment, PackageCollection $packages, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->config = $environment->getConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->rootPackageFile = $environment->getRootPackageFile();
        $this->repo = $environment->getRepository();
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
    }

    /**
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Adds a resource mapping to the repository.
     *
     * @param ResourceMapping $mapping The resource mapping.
     */
    public function addResourceMapping(ResourceMapping $mapping)
    {
        foreach ($mapping->getFilesystemPaths() as $relPath) {
            $absPath = Path::makeAbsolute($relPath, $this->rootDir);
            $resource = is_dir($absPath)
                ? new DirectoryResource($absPath)
                : new FileResource($absPath);

            $this->repo->add($mapping->getRepositoryPath(), $resource);
        }

        $this->rootPackageFile->addResourceMapping($mapping);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
    }

    /**
     * Builds the resource repository.
     *
     * @throws NoDirectoryException If the dump directory exists and is not a
     *                              directory.
     * @throws ResourceConflictException If two packages contain conflicting
     *                                   resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function buildRepository()
    {
        $repo = $this->environment->getRepository();

        $builder = new RepositoryBuilder();
        $builder->loadPackages($this->packages);

        if ($repo->hasChildren('/')) {
            $repo->clear();
        }

        $builder->buildRepository($repo);
    }
}
