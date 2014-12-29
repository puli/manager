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

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Collection\PackageCollection;

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
     * @var PackageCollection
     */
    private $packages;

    /**
     * Creates a repository manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     */
    public function __construct(ProjectEnvironment $environment, PackageCollection $packages)
    {
        $this->environment = $environment;
        $this->config = $environment->getConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->packages = $packages;
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
