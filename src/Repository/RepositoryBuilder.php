<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use Puli\Filesystem\Resource\LocalDirectoryResource;
use Puli\Filesystem\Resource\LocalFileResource;
use Puli\Filesystem\Resource\LocalResourceInterface;
use Puli\Repository\ManageableRepositoryInterface;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Graph\PackageNameGraph;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\Resource\DirectoryResourceInterface;

/**
 * Builds a resource repository from package configurations.
 *
 * First, load the packages with {@link loadPackages()}. This method will read
 * the package configurations and check for conflicts.
 *
 * Next, pass a manageable resource repository to {@link buildRepository()}.
 * This method adds all loaded resources to the repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryBuilder
{
    /**
     * @var PackageCollection|Package[]
     */
    private $packages;

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
     * @var array[]
     */
    private $tags = array();

    /**
     * Loads the resource configuration of all packages in the repository.
     *
     * This method will check whether the configuration of the different
     * packages contains conflicts. If yes, an exception is thrown.
     *
     * If this method succeeds, call {@link buildRepository()} to add the
     * loaded resources to a resource repository.
     *
     * @param PackageCollection $packages The packages.
     *
     * @throws ResourceConflictException If two packages contain conflicting
     *                                   resource definitions.
     * @throws ResourceDefinitionException If a resource definition is invalid.
     */
    public function loadPackages(PackageCollection $packages)
    {
        $this->packages = $packages;
        $this->packageGraph = new PackageNameGraph();
        $this->packageOverrides = array();
        $this->resources = array();
        $this->knownPaths = array();
        $this->tags = array();

        $this->loadPackageConfiguration();
        $this->buildPackageGraph();
        $this->detectConflicts();
    }

    /**
     * Adds all loaded resources to the given repository.
     *
     * Call {@link loadPackages()} first, otherwise this method will do nothing.
     *
     * @param ManageableRepositoryInterface $repo The repository that the
     *                                            loaded resources are added to.
     */
    public function buildRepository(ManageableRepositoryInterface $repo)
    {
        if (null === $this->packageGraph) {
            // Not loaded
            return;
        }

        $this->addResources($repo);
        $this->tagResources($repo);
    }

    private function loadPackageConfiguration()
    {
        foreach ($this->packages as $package) {
            $this->packageGraph->addPackageName($package->getName());

            $this->processResources($package);
            $this->processOverrides($package);
            $this->processTags($package);

            if ($package instanceof RootPackage) {
                $this->processPackageOrder($package->getConfig()->getPackageOrder());
            }
        }
    }

    private function processResources(Package $package)
    {
        $packageName = $package->getName();
        $config = $package->getConfig();

        if (!isset($this->resources[$packageName])) {
            $this->resources[$packageName] = array();
        }

        foreach ($config->getResourceDescriptors() as $descriptor) {
            $path = $descriptor->getPuliPath();
            $relativePaths = $descriptor->getLocalPaths();

            if (!isset($this->resources[$packageName][$path])) {
                $this->resources[$packageName][$path] = array();
            }

            foreach ($relativePaths as $relativePath) {
                // Reference to install path of other package
                if ('@' === $relativePath[0] && false !== ($pos = strpos($relativePath, ':'))) {
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
                            continue;
                        }

                        throw new ResourceDefinitionException(sprintf(
                            'The package "%s" referred to a non-existing '.
                            'package "%s" in the resource path "%s". Did you '.
                            'forget to require the package "%s"?',
                            $packageName,
                            $refPackageName,
                            $relativePath,
                            $refPackageName
                        ));
                    }

                    $refPackage = $this->packages->get($refPackageName);
                    $absolutePath = $refPackage->getInstallPath().'/'.substr($relativePath, $pos + 1);
                } else {
                    $absolutePath = $package->getInstallPath().'/'.$relativePath;
                }

                $resource = is_dir($absolutePath)
                    ? new LocalDirectoryResource($absolutePath)
                    : new LocalFileResource($absolutePath);

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

    private function prepareConflictDetection($path, LocalResourceInterface $resource, $currentPackageName)
    {
        if (!isset($this->knownPaths[$path])) {
            $this->knownPaths[$path] = array();
        }

        $this->knownPaths[$path][$currentPackageName] = true;

        // Detect conflicts in sub-directories
        if ($resource instanceof DirectoryResourceInterface) {
            $basePath = rtrim($path, '/').'/';
            foreach ($resource->listEntries() as $entry) {
                $this->prepareConflictDetection($basePath.basename($entry->getLocalPath()), $entry, $currentPackageName);
            }
        }
    }

    private function processOverrides(Package $package)
    {
        $packageName = $package->getName();
        $config = $package->getConfig();

        if (!isset($this->packageOverrides[$packageName])) {
            $this->packageOverrides[$packageName] = array();
        }

        foreach ($config->getOverriddenPackages() as $override) {
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

    private function processTags(Package $package)
    {
        $config = $package->getConfig();

        foreach ($config->getTagDescriptors() as $descriptor) {
            $selector = $descriptor->getPuliSelector();

            if (!isset($this->tags[$selector])) {
                $this->tags[$selector] = array();
            }

            foreach ($descriptor->getTags() as $tag) {
                // Store tags as keys to prevent duplicates
                $this->tags[$selector][$tag] = true;
            }
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

    private function addResources(ManageableRepositoryInterface $repo)
    {
        $packageOrder = $this->packageGraph->getSortedPackageNames();

        foreach ($packageOrder as $packageName) {
            if (!isset($this->resources[$packageName])) {
                continue;
            }

            foreach ($this->resources[$packageName] as $path => $resources) {
                foreach ($resources as $resource) {
                    $repo->add($path, $resource);
                }
            }
        }
    }

    private function tagResources(ManageableRepositoryInterface $repo)
    {
        foreach ($this->tags as $path => $tags) {
            foreach ($tags as $tag => $_) {
                $repo->tag($path, $tag);
            }
        }
    }
}
