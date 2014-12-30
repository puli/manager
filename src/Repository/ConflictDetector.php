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

use Puli\RepositoryManager\Package\Graph\PackageNameGraph;

/**
 * Detects resource mapping conflicts between packages.
 *
 * You need to pass all paths in the repository to {@link register()} together
 * with the package that registered the path. Whenever you want to look for
 * conflicts, call {@link markUnchecked()} with all paths that you want to
 * check. The detector will then check whether any of these paths have a
 * conflict according to the package graph passed to the constructor.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConflictDetector
{
    /**
     * @var PackageNameGraph
     */
    private $packageGraph;

    /**
     * @var bool[]
     */
    private $uncheckedPaths = array();

    /**
     * @var bool[][]
     */
    private $paths = array();

    /**
     * Creates a new conflict detector.
     *
     * @param PackageNameGraph $packageGraph The graph indicating which
     *                                       package is overridden by which
     *                                       other package.
     */
    public function __construct(PackageNameGraph $packageGraph)
    {
        $this->packageGraph = $packageGraph;
    }

    /**
     * Registers a path for a package.
     *
     * @param string $path        The repository path.
     * @param string $packageName The package name.
     */
    public function register($path, $packageName)
    {
        if (!isset($this->paths[$path])) {
            $this->paths[$path] = array();
        }

        $this->paths[$path][$packageName] = true;
    }

    /**
     * Unregisters a path for a package.
     *
     * @param string $path        The repository path.
     * @param string $packageName The package name.
     */
    public function unregister($path, $packageName)
    {
        unset($this->paths[$path][$packageName]);
    }

    /**
     * Marks a path as unchecked.
     *
     * All unchecked paths are being checked in {@link detectConflicts()}.
     *
     * @param string $path The repository path.
     */
    public function markUnchecked($path)
    {
        $this->uncheckedPaths[$path] = true;
    }

    /**
     * Checks all unchecked paths for conflicts.
     *
     * All paths which are found to be conflict-free are marked as checked. As
     * soon as a conflict is found, this method aborts. Call it again after
     * resolving the conflict in order to check that no more conflicts occur.
     *
     * @return ResourceConflict Returns the found conflict or `null` if no
     *                          conflict was found.
     */
    public function detectConflict()
    {
        foreach ($this->uncheckedPaths as $path => $true) {
            if (!isset($this->paths[$path])) {
                unset($this->uncheckedPaths[$path]);

                continue;
            }

            $packageNames = $this->paths[$path];

            if (1 === count($packageNames)) {
                unset($this->uncheckedPaths[$path]);

                continue;
            }

            $sortedNames = $this->packageGraph->getSortedPackageNames(array_keys($packageNames));

            // An edge must exist between each package pair in the sorted set,
            // otherwise the dependencies are not sufficiently defined
            for ($i = 1, $l = count($sortedNames); $i < $l; ++$i) {
                if (!$this->packageGraph->hasEdge($sortedNames[$i - 1], $sortedNames[$i])) {
                    return new ResourceConflict($path, $sortedNames[$i - 1], $sortedNames[$i]);
                }
            }

            unset($this->uncheckedPaths[$path]);
        }

        return null;
    }

}
