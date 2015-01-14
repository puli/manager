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
use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Conflict\OverrideGraph;
use Puli\RepositoryManager\Conflict\PackageConflictDetector;
use Puli\RepositoryManager\Conflict\PackageConflictException;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageCollection;
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
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var PackageConflictDetector
     */
    private $conflictDetector;

    /**
     * @var ResourceMappingStore
     */
    private $mappingStore;

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
     * Adds a resource mapping to the repository.
     *
     * @param ResourceMapping $mapping The resource mapping.
     */
    public function addResourceMapping(ResourceMapping $mapping)
    {
        if (!$this->overrideGraph) {
            $this->loadPackages();
        }

        if (!$this->repo) {
            $this->loadRepository();
        }

        $this->repoUpdater->clear();

        $rootPackageName = $this->rootPackage->getName();
        $absMapping = $this->makeAbsolute($mapping, $this->rootPackage);

        if ($absMapping) {
            $this->loadResourceMapping($absMapping, $this->rootPackage);
            $this->repoUpdater->add($absMapping, $rootPackageName);
        }

        while ($conflictingPackage = $this->getConflictingPackageName($rootPackageName)) {
            $this->rootPackageFile->addOverriddenPackage($conflictingPackage);
            $this->overrideGraph->addEdge($conflictingPackage, $rootPackageName);
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

        if (!$this->overrideGraph) {
            $this->loadPackages();
        }

        if (!$this->repo) {
            $this->loadRepository();
        }

        $this->repoUpdater->clear();

        if ($this->mappingStore->exists($repositoryPath, $this->rootPackage)) {
            $absMapping = $this->mappingStore->get($repositoryPath, $this->rootPackage);
            $this->unloadResourceMapping($absMapping, $this->rootPackage);
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
        if (!$this->overrideGraph) {
            $this->loadPackages();
        }

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
     * @throws PackageConflictException If two packages contain conflicting
     *                                  resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function buildRepository()
    {
        if (!$this->overrideGraph) {
            $this->loadPackages();
        }

        if (!$this->repo) {
            $this->loadRepository();
        }

        if ($this->repo->hasChildren('/')) {
            throw new RepositoryNotEmptyException('The repository is not empty.');
        }

        $this->repoUpdater->clear();

        foreach ($this->mappingStore->getRepositoryPaths() as $repositoryPath) {
            foreach ($this->mappingStore->getAll($repositoryPath) as $packageName => $mapping) {
                $this->repoUpdater->add($mapping, $packageName);
            }
        }

        $this->repoUpdater->commit();
    }

    /**
     * Clears the contents of the resource repository.
     */
    public function clearRepository()
    {
        if (!$this->repo) {
            $this->loadRepository();
        }

        $this->repo->clear();
    }

    /**
     * Loads the resource mappings and override settings for the installed
     * packages.
     *
     * The method processes the package configuration files and stores the
     * following information:
     *
     *  * The resources mappings of each package.
     *  * The override dependencies between the packages.
     *
     * Once all that information is loaded, the repository can be built by
     * loading the resource mappings of the packages in the order determined
     * by the override dependencies: First the resources of overridden packages
     * are added to the repository, then the resources of the overriding ones.
     *
     * If two packages map the same resource path without having an override
     * order specified between them, a conflict exception is raised. The
     * override order can be specified either by marking one package to override
     * the other one (see {@link PackageFile::setOverriddenPackages()} or by
     * setting the order between the packages in the root package file
     * (see RootPackageFile::setPackageOrder()}.
     *
     * This method is called automatically when necessary. You can however use
     * it to validate the configuration files of the packages without doing any
     * changes to them.
     *
     * @throws PackageConflictException If a resource conflict is detected.
     */
    private function loadPackages()
    {
        $this->overrideGraph = new OverrideGraph($this->packages->getPackageNames());
        $this->conflictDetector = new PackageConflictDetector($this->overrideGraph);
        $this->mappingStore = new ResourceMappingStore();

        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getResourceMappings() as $mapping) {
                if (!$absMapping = $this->makeAbsolute($mapping, $package)) {
                    continue;
                }

                $this->loadResourceMapping($absMapping, $package);
            }

            $this->loadOverrideOrder($package);
        }

        if ($conflict = $this->conflictDetector->detectConflict()) {
            throw PackageConflictException::forPathConflict($conflict);
        }
    }

    private function loadRepository()
    {
        if (!$this->overrideGraph) {
            $this->loadPackages();
        }

        $this->repo = $this->environment->getRepository();
        $this->repoUpdater = new RepositoryUpdater($this->repo, $this->overrideGraph);
    }

    private function loadResourceMapping(ResourceMapping $mapping, Package $package)
    {
        $iterator = $this->getMappingIterator($mapping);

        foreach ($iterator as $repositoryPath) {
            $this->mappingStore->add($repositoryPath, $package, $mapping);
            $this->conflictDetector->claim($repositoryPath, $package->getName());
        }
    }

    private function unloadResourceMapping(ResourceMapping $mapping, Package $package)
    {
        $iterator = $this->getMappingIterator($mapping);

        foreach ($iterator as $repositoryPath) {
            $this->mappingStore->remove($repositoryPath, $package);
            $this->conflictDetector->release($repositoryPath, $package->getName());

            if (!$this->mappingStore->existsAny($repositoryPath)) {
                continue;
            }

            // Reapply any overridden mappings
            foreach ($this->mappingStore->getAll($repositoryPath) as $overriddenPackage => $overriddenMapping) {
                $this->repoUpdater->add($overriddenMapping, $overriddenPackage);
            }
        }
    }

    private function loadOverrideOrder(Package $package)
    {
        foreach ($package->getPackageFile()->getOverriddenPackages() as $overriddenPackage) {
            if ($this->overrideGraph->hasPackageName($overriddenPackage)) {
                $this->overrideGraph->addEdge($overriddenPackage, $package->getName());
            }
        }

        if ($package instanceof RootPackage) {
            // Make sure we have numeric, ascending keys here
            $packageOrder = array_values($package->getPackageFile()->getOverrideOrder());

            // Each package overrides the previous one in the list
            for ($i = 1, $l = count($packageOrder); $i < $l; ++$i) {
                $overriddenPackage = $packageOrder[$i - 1];
                $overridingPackage = $packageOrder[$i];

                if ($this->overrideGraph->hasPackageName($overriddenPackage)) {
                    $this->overrideGraph->addEdge($overriddenPackage, $overridingPackage);
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
            throw PackageConflictException::forPathConflict($conflict);
        }

        return $conflict->getOpponent($packageName);
    }

    /**
     * @param ResourceMapping $mapping
     * @param Package         $package
     *
     * @return ResourceMapping|null
     */
    private function makeAbsolute(ResourceMapping $mapping, Package $package)
    {
        $filesystemPaths = array();

        foreach ($mapping->getFilesystemPaths() as $relativePath) {
            $absolutePath = $this->makeAbsolutePath($relativePath, $package);

            if (null === $absolutePath) {
                continue;
            }

            Assert::true(file_exists($absolutePath), sprintf(
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
    private function makeAbsolutePath($relativePath, Package $package)
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
