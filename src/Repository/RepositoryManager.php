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
use Puli\Repository\Api\Resource\FilesystemResource;
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
     * @var array[]
     */
    private $packageOverrides = array();

    /**
     * @var array[]
     */
    private $resources = array();

    /**
     * @var array[]
     */
    private $knownPaths = array();

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

        $this->loadPackageConfiguration();
        $this->buildPackageGraph();
        $this->detectConflicts();
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
        $resources = $this->getMappedResources($this->rootPackage, $mapping);

        foreach ($resources as $resource) {
            $this->repo->add($mapping->getRepositoryPath(), $resource);
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

        $packageOrder = $this->packageGraph->getSortedPackageNames();

        foreach ($packageOrder as $packageName) {
            if (!isset($this->resources[$packageName])) {
                continue;
            }

            foreach ($this->resources[$packageName] as $path => $resources) {
                foreach ($resources as $resource) {
                    $this->repo->add($path, $resource);
                }
            }
        }
    }

    private function loadPackageConfiguration()
    {
        $this->packageGraph = new PackageNameGraph();
        $this->packageOverrides = array();
        $this->resources = array();
        $this->knownPaths = array();

        // TODO check that a root package is present

        foreach ($this->packages as $package) {
            $this->packageGraph->addPackageName($package->getName());

            $this->processResources($package);
            $this->processOverrides($package);

            if ($package instanceof RootPackage) {
                $this->processPackageOrder($package->getPackageFile()->getPackageOrder());
            }
        }
    }

    private function processResources(Package $package)
    {
        $packageName = $package->getName();
        $packageFile = $package->getPackageFile();

        if (!isset($this->resources[$packageName])) {
            $this->resources[$packageName] = array();
        }

        foreach ($packageFile->getResourceMappings() as $mapping) {
            $path = $mapping->getRepositoryPath();
            $resources = $this->getMappedResources($package, $mapping);

            if (!isset($this->resources[$packageName][$path])) {
                $this->resources[$packageName][$path] = array();
            }

            foreach ($resources as $resource) {
                // Packages can set a repository path to multiple local paths
                $this->resources[$packageName][$path][] = $resource;

                // Store information necessary to detect conflicts later
                $this->prepareConflictDetection($path, $resource, $packageName);
            }
        }

        // Export shorter paths before longer paths
        if (isset($this->resources[$packageName])) {
            ksort($this->resources[$packageName]);
        }
    }

    private function prepareConflictDetection($path, FilesystemResource $resource, $currentPackageName)
    {
        if (!isset($this->knownPaths[$path])) {
            $this->knownPaths[$path] = array();
        }

        $this->knownPaths[$path][$currentPackageName] = true;

        // Detect conflicts in sub-directories
        if ($resource instanceof DirectoryResource) {
            $basePath = rtrim($path, '/').'/';
            foreach ($resource->listChildren() as $child) {
                $this->prepareConflictDetection($basePath.basename($child->getFilesystemPath()), $child, $currentPackageName);
            }
        }
    }

    private function processOverrides(Package $package)
    {
        $packageName = $package->getName();
        $packageFile = $package->getPackageFile();

        if (!isset($this->packageOverrides[$packageName])) {
            $this->packageOverrides[$packageName] = array();
        }

        foreach ($packageFile->getOverriddenPackages() as $override) {
            $this->packageOverrides[$packageName][] = $override;
        }
    }

    private function processPackageOrder(array $packageOrder)
    {
        // Make sure we have numeric, ascending keys here
        $packageOrder = array_values($packageOrder);

        // Each package overrides the previous one in the list
        for ($i = 1, $l = count($packageOrder); $i < $l; ++$i) {
            if (!isset($this->packageOverrides[$packageOrder[$i]])) {
                $this->packageOverrides[$packageOrder[$i]] = array();
            }

            $this->packageOverrides[$packageOrder[$i]][] = $packageOrder[$i - 1];
        }
    }

    private function buildPackageGraph()
    {
        foreach ($this->packageOverrides as $overridingPackage => $overriddenPackages) {
            foreach ($overriddenPackages as $overriddenPackage) {
                // The overridden package must be processed before the
                // overriding package
                // Check that the overridden package is actually loaded TODO test
                if ($this->packageGraph->hasPackageName($overriddenPackage)) {
                    $this->packageGraph->addEdge($overriddenPackage, $overridingPackage);
                }
            }
        }

        // Free unneeded space
        unset($this->packageOverrides);
    }

    private function detectConflicts()
    {
        // Check whether any of the paths were registered by more than one
        // package and if yes, check if the order between the packages is
        // defined
        foreach ($this->knownPaths as $path => $packageNames) {
            // Attention, the package names are stored in the keys
            if (1 === count($packageNames)) {
                continue;
            }

            $orderedNames = $this->packageGraph->getSortedPackageNames(array_keys($packageNames));

            // An edge must exist between each package pair in the sorted set,
            // otherwise the dependencies are not sufficiently defined
            for ($i = 1, $l = count($orderedNames); $i < $l; ++$i) {
                if (!$this->packageGraph->hasEdge($orderedNames[$i - 1], $orderedNames[$i])) {
                    throw new ResourceConflictException(sprintf(
                        'The packages "%s" and "%s" add resources for the same '.
                        'path "%s", but have no override order defined '.
                        "between them.\n\nResolutions:\n\n(1) Add the key ".
                        '"override" to the composer.json of one package and '.
                        "set its value to the other package name.\n(2) Add the ".
                        'key "override-order" to the composer.json of the root '.
                        'package and define the order of the packages there.',
                        $orderedNames[$i - 1],
                        $orderedNames[$i],
                        $path
                    ));
                }
            }
        }
    }

    /**
     * @param Package $package
     * @param string  $relativePath
     *
     * @return null|string
     */
    private function getAbsoluteFilesystemPath(Package $package, $relativePath)
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
     * @param Package         $package
     * @param ResourceMapping $mapping
     *
     * @return array
     */
    private function getMappedResources(Package $package, ResourceMapping $mapping)
    {
        $resources = array();

        foreach ($mapping->getFilesystemPaths() as $relativePath) {
            $absolutePath = $this->getAbsoluteFilesystemPath($package, $relativePath);

            if (null === $absolutePath) {
                continue;
            }

            $resources[] = is_dir($absolutePath)
                ? new DirectoryResource($absolutePath)
                : new FileResource($absolutePath);
        }

        return $resources;
    }
}
