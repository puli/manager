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

use ArrayIterator;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Assert\Assertion;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Graph\PackageNameGraph;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Repository\Iterator\RecursivePathsIterator;
use RecursiveIteratorIterator;

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
     * @var RepositoryUpdater
     */
    private $repoUpdater;

    /**
     * @var PackageCollection|Package[]
     */
    private $packages;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PackageNameGraph
     */
    private $packageGraph;

    /**
     * @var ConflictDetector
     */
    private $conflictDetector;

    /**
     * @var ResourceMapping[][]
     */
    private $mappingsByPath = array();

    /**
     * Creates a repository manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     * @param PackageFileStorage $packageFileStorage
     */
    public function __construct(ProjectEnvironment $environment, PackageCollection $packages, PackageFileStorage $packageFileStorage)
    {
        $this->environment = $environment;
        $this->config = $environment->getConfig();
        $this->rootDir = $environment->getRootDirectory();
        $this->rootPackage = $packages->getRootPackage();
        $this->rootPackageFile = $environment->getRootPackageFile();
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
     * Loads the resource mappings from the packages.
     *
     * This method is called automatically when necessary. You can however use
     * it to validate the package configuration without doing any changes.
     *
     * @throws ResourceConflictException If a resource conflict is detected.
     */
    public function loadPackages()
    {
        if (!$this->repo) {
            $this->loadRepository();
        }

        $this->packageGraph = new PackageNameGraph($this->packages->getPackageNames());
        $this->repoUpdater = new RepositoryUpdater($this->repo, $this->packageGraph);
        $this->conflictDetector = new ConflictDetector($this->packageGraph);

        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getResourceMappings() as $mapping) {
                if (!$absMapping = $this->getAbsoluteMapping($mapping, $package)) {
                    continue;
                }

                $this->loadResourceMapping($absMapping, $package->getName());
            }

            $this->loadOverrideOrder($package);
        }

        if ($conflict = $this->conflictDetector->detectConflict()) {
            throw ResourceConflictException::forConflict($conflict);
        }
    }

    /**
     * Adds a resource mapping to the repository.
     *
     * @param ResourceMapping $mapping The resource mapping.
     */
    public function addResourceMapping(ResourceMapping $mapping)
    {
        if (!$this->packageGraph) {
            $this->loadPackages();
        }

        $this->repoUpdater->clear();

        $rootPackageName = $this->rootPackage->getName();
        $absMapping = $this->getAbsoluteMapping($mapping, $this->rootPackage);

        if ($absMapping) {
            $this->loadResourceMapping($absMapping, $rootPackageName);
            $this->repoUpdater->add($absMapping, $rootPackageName);
        }

        while ($conflictingPackage = $this->getConflictingPackageName($rootPackageName)) {
            $this->rootPackageFile->addOverriddenPackage($conflictingPackage);
            $this->packageGraph->addEdge($conflictingPackage, $rootPackageName);
        }

        // Save config file before modifying the repository, so that the
        // repository can be rebuilt *with* the changes on failures
        $this->rootPackageFile->addResourceMapping($mapping);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        // Now update the repository
        $this->repoUpdater->commit();
    }

    /**
     * Removes a resource mapping from the repository.
     *
     * @param string $repositoryPath The repository path.
     */
    public function removeResourceMapping($repositoryPath)
    {
        if (!$this->rootPackageFile->hasResourceMapping($repositoryPath)) {
            return;
        }

        if (!$this->packageGraph) {
            $this->loadPackages();
        }

        $this->repoUpdater->clear();

        $rootPackageName = $this->rootPackage->getName();

        if (isset($this->mappingsByPath[$repositoryPath][$rootPackageName])) {
            $absMapping = $this->mappingsByPath[$repositoryPath][$rootPackageName];
            $this->unloadResourceMapping($absMapping, $rootPackageName);
        }

        // Save config file before modifying the repository, so that the
        // repository can be rebuilt *with* the changes on failures
        $this->rootPackageFile->removeResourceMapping($repositoryPath);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);

        // Now update the repository
        $this->repo->remove($repositoryPath);
        $this->repoUpdater->commit();
    }

    /**
     * Returns whether a repository path is mapped.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return bool Returns `true` if the repository path is mapped.
     */
    public function hasResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->hasResourceMapping($repositoryPath);
    }

    /**
     * Returns the resource mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return ResourceMapping The corresponding resource mapping.
     *
     * @throws NoSuchMappingException If the repository path is not mapped.
     */
    public function getResourceMapping($repositoryPath)
    {
        return $this->rootPackageFile->getResourceMapping($repositoryPath);
    }

    /**
     * Returns the resource mappings.
     *
     * @param string|string[] $packageName The package name(s) to filter by.
     *
     * @return ResourceMapping[] The resource mappings.
     */
    public function getResourceMappings($packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $mappings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getResourceMappings() as $mapping) {
                $mappings[] = $mapping;
            }
        }

        return $mappings;
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
        if (!$this->repo) {
            $this->loadRepository();
        }

        if ($this->repo->hasChildren('/')) {
            // quit
        }

        if (!$this->packageGraph) {
            $this->loadPackages();
        }

        $this->repoUpdater->clear();

        foreach ($this->mappingsByPath as $repositoryPath => $mappings) {
            foreach ($mappings as $packageName => $mapping) {
                $this->repoUpdater->add($mapping, $packageName);
            }
        }

        $this->repoUpdater->commit();
    }

    private function loadRepository()
    {
        $this->repo = $this->environment->getRepository();
    }

    /**
     * @param ResourceMapping $mapping
     * @param string          $packageName
     */
    private function loadResourceMapping(ResourceMapping $mapping, $packageName)
    {
        $iterator = $this->getMappingIterator($mapping);

        foreach ($iterator as $filesystemPath => $repositoryPath) {
            if (!isset($this->mappingsByPath[$repositoryPath])) {
                $this->mappingsByPath[$repositoryPath] = array();
            }

            $this->mappingsByPath[$repositoryPath][$packageName] = $mapping;

            $this->conflictDetector->register($repositoryPath, $packageName);
            $this->conflictDetector->markUnchecked($repositoryPath);
        }
    }

    private function unloadResourceMapping(ResourceMapping $mapping, $packageName)
    {
        $iterator = $this->getMappingIterator($mapping);

        foreach ($iterator as $filesystemPath => $repositoryPath) {
            $this->conflictDetector->unregister($repositoryPath, $packageName);

            unset($this->mappingsByPath[$repositoryPath][$packageName]);

            // Reapply any overridden mappings
            foreach ($this->mappingsByPath[$repositoryPath] as $overriddenPackage => $overriddenMapping) {
                $this->repoUpdater->add($overriddenMapping, $overriddenPackage);
            }
        }
    }

    private function loadOverrideOrder(Package $package)
    {
        foreach ($package->getPackageFile()->getOverriddenPackages() as $overriddenPackage) {
            if ($this->packageGraph->hasPackageName($overriddenPackage)) {
                $this->packageGraph->addEdge($overriddenPackage, $package->getName());
            }
        }

        if ($package instanceof RootPackage) {
            // Make sure we have numeric, ascending keys here
            $packageOrder = array_values($package->getPackageFile()->getPackageOrder());

            // Each package overrides the previous one in the list
            for ($i = 1, $l = count($packageOrder); $i < $l; ++$i) {
                $overriddenPackage = $packageOrder[$i - 1];
                $overridingPackage = $packageOrder[$i];

                if ($this->packageGraph->hasPackageName($overriddenPackage)) {
                    $this->packageGraph->addEdge($overriddenPackage, $overridingPackage);
                }
            }
        }
    }

    private function getConflictingPackageName($packageName)
    {
        $conflict = $this->conflictDetector->detectConflict();

        if (!$conflict) {
            return null;
        }

        // We are only interested in conflicts that involve this package
        if (!$conflict->involvesPackage($packageName)) {
            throw ResourceConflictException::forConflict($conflict);
        }

        return $conflict->getOpponent($packageName);
    }

    /**
     * @param ResourceMapping $mapping
     * @param Package         $package
     *
     * @return ResourceMapping|null
     */
    private function getAbsoluteMapping(ResourceMapping $mapping, Package $package)
    {
        $filesystemPaths = array();

        foreach ($mapping->getFilesystemPaths() as $relativePath) {
            $absolutePath = $this->makeAbsolute($relativePath, $package);

            if (null === $absolutePath) {
                continue;
            }

            Assertion::true(file_exists($absolutePath), sprintf(
                'The path %s mapped to %s by package "%s" does not exist.',
                $relativePath,
                $mapping->getRepositoryPath(),
                $package->getName()
            ));

            $filesystemPaths[] = $absolutePath;
        }

        if (!$filesystemPaths) {
            return null;
        }

        return new ResourceMapping($mapping->getRepositoryPath(), $filesystemPaths);
    }

    /**
     * @param string  $relativePath
     * @param Package $package
     *
     * @return null|string
     */
    private function makeAbsolute($relativePath, Package $package)
    {
        // Reference to install path of other package
        if ('@' !== $relativePath[0] || false === ($pos = strpos($relativePath, ':'))) {
            return $package->getInstallPath().'/'.$relativePath;
        }

        $refPackageName = substr($relativePath, 1, $pos - 1);
        $optional = false;

        // Package references can be made optional by prefixing
        // with "@?" instead of just "@"
        // Useful for suggested packages, for example
        if ('?' === $refPackageName[0]) {
            $refPackageName = substr($refPackageName, 1);
            $optional = true;
        }

        if (!$this->packages->contains($refPackageName)) {
            if ($optional) {
                return null;
            }

            throw new ResourceDefinitionException(sprintf(
                'The package "%s" referred to a non-existing '.
                'package "%s" in the resource path "%s". Did you '.
                'forget to require the package "%s"?',
                $package->getName(),
                $refPackageName,
                $relativePath,
                $refPackageName
            ));
        }

        $refPackage = $this->packages->get($refPackageName);

        return $refPackage->getInstallPath().'/'.substr($relativePath, $pos + 1);
    }

    /**
     * @param ResourceMapping $mapping
     *
     * @return RecursiveIteratorIterator
     */
    private function getMappingIterator(ResourceMapping $mapping)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursivePathsIterator(
                new ArrayIterator($mapping->getFilesystemPaths()),
                $mapping->getRepositoryPath()
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        return $iterator;
    }
}
