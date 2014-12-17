<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\Graph;

use InvalidArgumentException;

/**
 * A directed, acyclic graph of package names.
 *
 * Packages can be added with {@link addInstalledPackage()}. Edges between these packages
 * can then be added using {@link addEdge()}. Both ends of an edge must have
 * been defined before the edge is added.
 *
 * ```php
 * $graph = new PackageNameGraph();
 * $graph->addInstalledPackage('acme/core');
 * $graph->addInstalledPackage('acme/blog');
 * $graph->addInstalledPackage('acme/blog-extension1');
 * $graph->addInstalledPackage('acme/blog-extension2');
 * $graph->addEdge('acme/core', 'acme/blog');
 * $graph->addEdge('acme/blog', 'acme/blog-extension1');
 * $graph->addEdge('acme/blog', 'acme/blog-extension2');
 * $graph->addEdge('acme/blog-extension1', 'acme/blog-extension2');
 * ```
 *
 * You can use {@link getPath()} and {@link hasPath()} to check whether a path
 * exists from one package to the other:
 *
 * ```php
 * // ...
 *
 * $graph->hasPath('acme/blog', 'acme/blog-extension1');
 * // => true
 *
 * $graph->hasPath('acme/blog-extension1', 'acme/blog-extension2');
 * // => false
 *
 * $graph->getPath('acme/core', 'acme/blog-extension2');
 * // => array('acme/core', 'acme/blog', 'acme/blog-extension2')
 * ```
 *
 * With {@link getSortedPackageNames()}, you can sort the packages such that the
 * dependencies defined via the edges are respected:
 *
 * ```php
 * // ...
 *
 * $graph->getSortedPackageNames();
 * // => array('acme/core', 'acme/blog', 'acme/blog-extension1', 'acme/blog-extension2')
 * ```
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageNameGraph
{
    /**
     * Stores the names of all packages (vertices) as keys.
     *
     * @var array
     */
    private $packageNames = array();

    /**
     * Stores the edges in the keys of a multi-dimensional array.
     *
     * The first dimension stores the targets, the second dimension the origins
     * of the edges.
     *
     * @var array
     */
    private $edges = array();

    /**
     * Adds a package name to the graph.
     *
     * @param string $packageName The package name.
     *
     * @throws InvalidArgumentException If the package name already exists.
     */
    public function addPackageName($packageName)
    {
        if (isset($this->packageNames[$packageName])) {
            throw new InvalidArgumentException(sprintf(
                'The package "%s" was added to the graph twice.',
                $packageName
            ));
        }

        $this->packageNames[$packageName] = true;
        $this->edges[$packageName] = array();
    }

    /**
     * Returns whether a package name exists in the graph.
     *
     * @param string $packageName The package name.
     *
     * @return bool Whether the package name exists.
     */
    public function hasPackageName($packageName)
    {
        return isset($this->packageNames[$packageName]);
    }

    /**
     * Adds a directed edge from one to another package.
     *
     * @param string $from The start package name.
     * @param string $to   The end package name.
     *
     * @throws InvalidArgumentException If any of the packages does not exist
     *                                   in the graph. Each package must have
     *                                   been added first.
     *
     * @throws CycleException If adding the edge would create a cycle.
     */
    public function addEdge($from, $to)
    {
        if (!isset($this->packageNames[$from])) {
            throw new InvalidArgumentException(sprintf(
                'The package "%s" does not exist in the graph.',
                $from
            ));
        }

        if (!isset($this->packageNames[$to])) {
            throw new InvalidArgumentException(sprintf(
                'The package "%s" does not exist in the graph.',
                $to
            ));
        }

        if (null !== ($path = $this->getPath($to, $from))) {
            $last = array_pop($path);

            throw new CycleException(sprintf(
                'A cyclic dependency was discovered between the packages "%s" '.
                'and "%s". Please check the "override" keys defined in these'.
                'packages.',
                implode('", "', $path),
                $last
            ));
        }

        $this->edges[$to][$from] = true;
    }

    /**
     * Returns whether an edge exists between two packages.
     *
     * @param string $from The start package name.
     * @param string $to   The end package name.
     *
     * @return bool Whether an edge exists from the origin to the target package.
     */
    public function hasEdge($from, $to)
    {
        return isset($this->edges[$to][$from]);
    }

    /**
     * Returns whether a path exists from one to another package.
     *
     * @param string $from The start package name.
     * @param string $to   The end package name.
     *
     * @return bool Whether a path exists from the origin to the target package.
     */
    public function hasPath($from, $to)
    {
        // does not exist in the graph
        if (!isset($this->edges[$to])) {
            return false;
        }

        // adjacent node
        if (isset($this->edges[$to][$from])) {
            return true;
        }

        // DFS
        foreach ($this->edges[$to] as $predecessor => $_) {
            if ($this->hasPath($from, $predecessor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the path from one to another package.
     *
     * @param string $from The start package name.
     * @param string $to   The end package name.
     *
     * @return string[]|null The path of package names or `null`, if no path
     *                       was found.
     */
    public function getPath($from, $to)
    {
        if ($this->getPathDFS($from, $to, $reversePath)) {
            return array_reverse($reversePath);
        }

        return null;
    }

    /**
     * Returns all package names in the graph.
     *
     * @return string All package names in the graph.
     */
    public function getPackageNames()
    {
        return $this->packageNames;
    }

    /**
     * Sorts package names according to the defined edges.
     *
     * The names are sorted such that if two packages p1 and p2 have an edge
     * (p1, p2) in the graph, then p1 comes before p2 in the sorted set.
     *
     * If no package names are passed, all names are sorted.
     *
     * @param string[] $namesToSort The package names which should be sorted.
     *
     * @return string[] The sorted package names.
     *
     * @throws InvalidArgumentException If any of the passed package names does
     *                                   not exist in the graph.
     */
    public function getSortedPackageNames(array $namesToSort = array())
    {
        if (count($namesToSort) > 0) {
            $namesToSort = array_flip($namesToSort);

            foreach ($namesToSort as $package => $_) {
                if (!isset($this->packageNames[$package])) {
                    throw new InvalidArgumentException(sprintf(
                        'The package "%s" does not exist in the graph.',
                        $package
                    ));
                }
            }
        } else {
            $namesToSort = $this->packageNames;
        }

        $sorted = array();

        // Do a topologic sort
        // Start with any package and process until no more are left
        while (false !== reset($namesToSort)) {
            $this->sortPackagesDFS(key($namesToSort), $namesToSort, $sorted);
        }

        return $sorted;
    }

    /**
     * Finds a path between packages using Depth-First Search.
     *
     * @param string $from        The start package name.
     * @param string $to          The end package name.
     * @param array  $reversePath The path in reverse order.
     *
     * @return bool Whether a path was found.
     */
    private function getPathDFS($from, $to, &$reversePath = array())
    {
        // does not exist in the graph
        if (!isset($this->edges[$to])) {
            return false;
        }

        $reversePath[] = $to;

        // adjacent node
        if (isset($this->edges[$to][$from])) {
            $reversePath[] = $from;

            return true;
        }

        // DFS
        foreach ($this->edges[$to] as $predecessor => $_) {
            if ($this->getPathDFS($from, $predecessor, $reversePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Topologically sorts the given package name into the output array.
     *
     * The resulting array is sorted such that all predecessors of the package
     * come before the package (and their predecessors before them, and so on).
     *
     * @param string $currentName The current package name to sort.
     * @param array  $namesToSort The package names yet to be sorted.
     * @param array  $output      The output array.
     */
    private function sortPackagesDFS($currentName, array &$namesToSort, array &$output)
    {
        unset($namesToSort[$currentName]);

        // Before adding the package itself to the path, add all predecessors.
        // Do so recursively, then we make sure that each package is visited
        // in the path before any of its successors.
        foreach ($this->edges[$currentName] as $predecessor => $_) {
            // The package was already processed. Either the package is on the
            // path already, then we're good. Otherwise, we have a cycle.
            // However, addEdge() guarantees that the graph is cycle-free.
            if (isset($namesToSort[$predecessor])) {
                $this->sortPackagesDFS($predecessor, $namesToSort, $output);
            }
        }

        $output[] = $currentName;
    }
}
