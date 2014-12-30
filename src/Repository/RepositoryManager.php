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
use Puli\Repository\Assert\Assertion;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Graph\PackageNameGraph;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Webmozart\Glob\Iterator\RecursiveDirectoryIterator;

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
    private $conflictGraph;

    /**
     * @var string[][][]
     */
    private $filesystemPaths = array();

    /**
     * @var bool[][]
     */
    private $pathReferences = array();

    /**
     * @var bool[]
     */
    private $uncheckedPaths = array();

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
        $this->repo = $environment->getRepository();
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;
        $this->conflictGraph = new PackageNameGraph($this->packages->getPackageNames());

        foreach ($packages as $package) {
            foreach ($package->getPackageFile()->getResourceMappings() as $mapping) {
                $this->loadResourceMapping($package, $mapping);
            }

            $this->loadOverrideOrder($package);
        }

        if ($conflict = $this->detectConflict()) {
            throw ResourceConflictException::forConflict($conflict);
        }
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
        $this->loadResourceMapping($this->rootPackage, $mapping);

        $rootPackageName = $this->rootPackage->getName();
        $path = $mapping->getRepositoryPath();

        while ($conflictingPackage = $this->getConflictingPackageName($rootPackageName)) {
            $this->rootPackageFile->addOverriddenPackage($conflictingPackage);
            $this->conflictGraph->addEdge($conflictingPackage, $rootPackageName);
        }

        foreach ($this->filesystemPaths[$rootPackageName][$path] as $filesystemPath) {
            $this->repo->add($path, $this->createResource($filesystemPath));
        }

        $this->rootPackageFile->addResourceMapping($mapping);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
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

        $this->repo->remove($repositoryPath);

        $this->rootPackageFile->removeResourceMapping($repositoryPath);
        $this->packageFileStorage->saveRootPackageFile($this->rootPackageFile);
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
        if ($this->repo->hasChildren('/')) {
            // quit
        }

        $packageOrder = $this->conflictGraph->getSortedPackageNames();

        foreach ($packageOrder as $packageName) {
            if (!isset($this->filesystemPaths[$packageName])) {
                continue;
            }

            foreach ($this->filesystemPaths[$packageName] as $path => $filesystemPaths) {
                foreach ($filesystemPaths as $filesystemPath) {
                    $this->repo->add($path, $this->createResource($filesystemPath));
                }
            }
        }
    }

    /**
     * @param Package $package
     * @param         $mapping
     */
    private function loadResourceMapping(Package $package, ResourceMapping $mapping)
    {
        $path = $mapping->getRepositoryPath();
        $packageName = $package->getName();
        $filesystemPaths = $this->getMappedPaths($mapping, $package);

        if (!isset($this->filesystemPaths[$packageName])) {
            $this->filesystemPaths[$packageName] = array();
        }

        foreach ($filesystemPaths as $filesystemPath) {
            $this->markPathUnchecked($path, $filesystemPath, $package);
        }

        $this->filesystemPaths[$packageName][$path] = $filesystemPaths;

        // Export shorter paths before longer paths
        ksort($this->filesystemPaths[$packageName]);
    }

    private function markPathUnchecked($path, $filesystemPath, Package $package)
    {
        if (!isset($this->pathReferences[$path])) {
            $this->pathReferences[$path] = array();
        }

        // Check this path in detectConflict()
        $this->uncheckedPaths[$path] = true;

        // Remember which packages reference the path
        $this->pathReferences[$path][$package->getName()] = true;

        if (!is_dir($filesystemPath)) {
            return;
        }

        // Mark nested paths as unchecked as well
        $iterator = new RecursiveDirectoryIterator($filesystemPath);
        $basePath = rtrim($path, '/').'/';

        foreach ($iterator as $entry) {
            $this->markPathUnchecked($basePath.basename($entry), $entry, $package);
        }
    }

    private function loadOverrideOrder(Package $package)
    {
        foreach ($package->getPackageFile()->getOverriddenPackages() as $overriddenPackage) {
            if ($this->conflictGraph->hasPackageName($overriddenPackage)) {
                $this->conflictGraph->addEdge($overriddenPackage, $package->getName());
            }
        }

        if ($package instanceof RootPackage) {
            // Make sure we have numeric, ascending keys here
            $packageOrder = array_values($package->getPackageFile()->getPackageOrder());

            // Each package overrides the previous one in the list
            for ($i = 1, $l = count($packageOrder); $i < $l; ++$i) {
                $overriddenPackage = $packageOrder[$i - 1];
                $overridingPackage = $packageOrder[$i];

                if ($this->conflictGraph->hasPackageName($overriddenPackage)) {
                    $this->conflictGraph->addEdge($overriddenPackage, $overridingPackage);
                }
            }
        }
    }

    private function detectConflict()
    {
        foreach ($this->uncheckedPaths as $path => $true) {
            $packageNames = $this->pathReferences[$path];

            if (1 === count($packageNames)) {
                continue;
            }

            // Attention, the package names are stored in the keys
            $orderedNames = $this->conflictGraph->getSortedPackageNames(array_keys($packageNames));

            // An edge must exist between each package pair in the sorted set,
            // otherwise the dependencies are not sufficiently defined
            for ($i = 1, $l = count($orderedNames); $i < $l; ++$i) {
                if (!$this->conflictGraph->hasEdge($orderedNames[$i - 1], $orderedNames[$i])) {
                    return new ResourceConflict($path, $orderedNames[$i - 1], $orderedNames[$i]);
                }
            }

            // Mark path as checked
            unset($this->uncheckedPaths[$path]);
        }

        return null;
    }

    private function getConflictingPackageName($packageName)
    {
        $conflict = $this->detectConflict();

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
     * @param Package         $package
     *
     * @return array
     */
    private function getMappedPaths(ResourceMapping $mapping, Package $package)
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

        return $filesystemPaths;
    }

    /**
     * @param $filesystemPath
     *
     * @return Resource
     */
    private function createResource($filesystemPath)
    {
        return is_dir($filesystemPath)
            ? new DirectoryResource($filesystemPath)
            : new FileResource($filesystemPath);
    }
}
