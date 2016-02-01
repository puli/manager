<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Conflict;

use Puli\Manager\Api\Module\ModuleList;
use RuntimeException;

/**
 * A directed, acyclic graph of module names.
 *
 * Modules can be added with {@link addModuleName()}. Edges between these modules
 * can then be added using {@link addEdge()}. Both ends of an edge must have
 * been defined before the edge is added.
 *
 * ```php
 * $graph = new OverrideGraph();
 * $graph->addModuleName('acme/core');
 * $graph->addModuleName('acme/blog');
 * $graph->addModuleName('acme/blog-extension1');
 * $graph->addModuleName('acme/blog-extension2');
 * $graph->addEdge('acme/core', 'acme/blog');
 * $graph->addEdge('acme/blog', 'acme/blog-extension1');
 * $graph->addEdge('acme/blog', 'acme/blog-extension2');
 * $graph->addEdge('acme/blog-extension1', 'acme/blog-extension2');
 * ```
 *
 * You can use {@link getPath()} and {@link hasPath()} to check whether a path
 * exists from one module to the other:
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
 * With {@link getSortedModuleNames()}, you can sort the modules such that the
 * dependencies defined via the edges are respected:
 *
 * ```php
 * // ...
 *
 * $graph->getSortedModuleNames();
 * // => array('acme/core', 'acme/blog', 'acme/blog-extension1', 'acme/blog-extension2')
 * ```
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OverrideGraph
{
    /**
     * Stores the names of all modules (vertices) as keys.
     *
     * @var array
     */
    private $moduleNames = array();

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
     * Creates an override graph for the given modules.
     *
     * @param ModuleList $modules The modules to load.
     *
     * @return static The created override graph.
     */
    public static function forModules(ModuleList $modules)
    {
        $graph = new static($modules->getModuleNames());

        foreach ($modules as $module) {
            if (null === $module->getModuleFile()) {
                continue;
            }

            foreach ($module->getModuleFile()->getOverriddenModules() as $overriddenModule) {
                if ($graph->hasModuleName($overriddenModule)) {
                    $graph->addEdge($overriddenModule, $module->getName());
                }
            }
        }

        // Do we have a root module?
        if (null === $modules->getRootModule()) {
            return $graph;
        }

        // Make sure we have numeric, ascending keys here
        $moduleOrder = array_values($modules->getRootModule()->getModuleFile()->getOverrideOrder());

        // Each module overrides the previous one in the list
        for ($i = 1, $l = count($moduleOrder); $i < $l; ++$i) {
            $overriddenModule = $moduleOrder[$i - 1];
            $overridingModule = $moduleOrder[$i];

            if ($graph->hasModuleName($overriddenModule)) {
                $graph->addEdge($overriddenModule, $overridingModule);
            }
        }

        return $graph;
    }

    /**
     * Creates a new graph.
     *
     * @param string[] $moduleNames The module names stored in the nodes of
     *                              the graph.
     */
    public function __construct(array $moduleNames = array())
    {
        $this->addModuleNames($moduleNames);
    }

    /**
     * Adds a module name to the graph.
     *
     * @param string $moduleName The module name.
     *
     * @throws RuntimeException If the module name already exists.
     */
    public function addModuleName($moduleName)
    {
        if (isset($this->moduleNames[$moduleName])) {
            throw new RuntimeException(sprintf(
                'The module "%s" was added to the graph twice.',
                $moduleName
            ));
        }

        $this->moduleNames[$moduleName] = true;
        $this->edges[$moduleName] = array();
    }

    /**
     * Adds a list of module names to the graph.
     *
     * @param string[] $moduleNames The module names.
     *
     * @throws RuntimeException If a module name already exists.
     */
    public function addModuleNames(array $moduleNames)
    {
        foreach ($moduleNames as $moduleName) {
            $this->addModuleName($moduleName);
        }
    }

    /**
     * Returns whether a module name exists in the graph.
     *
     * @param string $moduleName The module name.
     *
     * @return bool Whether the module name exists.
     */
    public function hasModuleName($moduleName)
    {
        return isset($this->moduleNames[$moduleName]);
    }

    /**
     * Adds a directed edge from one to another module.
     *
     * @param string $from The start module name.
     * @param string $to   The end module name.
     *
     * @throws RuntimeException          If any of the modules does not exist in the
     *                                   graph. Each module must have been added first.
     * @throws CyclicDependencyException If adding the edge would create a cycle.
     */
    public function addEdge($from, $to)
    {
        if (!isset($this->moduleNames[$from])) {
            throw new RuntimeException(sprintf(
                'The module "%s" does not exist in the graph.',
                $from
            ));
        }

        if (!isset($this->moduleNames[$to])) {
            throw new RuntimeException(sprintf(
                'The module "%s" does not exist in the graph.',
                $to
            ));
        }

        if (null !== ($path = $this->getPath($to, $from))) {
            $last = array_pop($path);

            throw new CyclicDependencyException(sprintf(
                'A cyclic dependency was discovered between the modules "%s" '.
                'and "%s". Please check the "override" keys defined in these '.
                'modules.',
                implode('", "', $path),
                $last
            ));
        }

        $this->edges[$to][$from] = true;
    }

    public function removeEdge($from, $to)
    {
        unset($this->edges[$to][$from]);
    }

    /**
     * Returns whether an edge exists between two modules.
     *
     * @param string $from The start module name.
     * @param string $to   The end module name.
     *
     * @return bool Whether an edge exists from the origin to the target module.
     */
    public function hasEdge($from, $to)
    {
        return isset($this->edges[$to][$from]);
    }

    /**
     * Returns whether a path exists from one to another module.
     *
     * @param string $from The start module name.
     * @param string $to   The end module name.
     *
     * @return bool Whether a path exists from the origin to the target module.
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
     * Returns the path from one to another module.
     *
     * @param string $from The start module name.
     * @param string $to   The end module name.
     *
     * @return string[]|null The path of module names or `null`, if no path
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
     * Returns all module names in the graph.
     *
     * @return array All module names in the graph.
     */
    public function getModuleNames()
    {
        return $this->moduleNames;
    }

    /**
     * Sorts module names according to the defined edges.
     *
     * The names are sorted such that if two modules p1 and p2 have an edge
     * (p1, p2) in the graph, then p1 comes before p2 in the sorted set.
     *
     * If no module names are passed, all names are sorted.
     *
     * @param string[] $namesToSort The module names which should be sorted.
     *
     * @return string[] The sorted module names.
     *
     * @throws RuntimeException If any of the passed module names does not
     *                          exist in the graph.
     */
    public function getSortedModuleNames(array $namesToSort = array())
    {
        if (count($namesToSort) > 0) {
            $namesToSort = array_flip($namesToSort);

            foreach ($namesToSort as $module => $_) {
                if (!isset($this->moduleNames[$module])) {
                    throw new RuntimeException(sprintf(
                        'The module "%s" does not exist in the graph.',
                        $module
                    ));
                }
            }
        } else {
            $namesToSort = $this->moduleNames;
        }

        $sorted = array();

        // Do a topologic sort
        // Start with any module and process until no more are left
        while (false !== reset($namesToSort)) {
            $this->sortModulesDFS(key($namesToSort), $namesToSort, $sorted);
        }

        return $sorted;
    }

    /**
     * Finds a path between modules using Depth-First Search.
     *
     * @param string $from        The start module name.
     * @param string $to          The end module name.
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
     * Topologically sorts the given module name into the output array.
     *
     * The resulting array is sorted such that all predecessors of the module
     * come before the module (and their predecessors before them, and so on).
     *
     * @param string $currentName The current module name to sort.
     * @param array  $namesToSort The module names yet to be sorted.
     * @param array  $output      The output array.
     */
    private function sortModulesDFS($currentName, array &$namesToSort, array &$output)
    {
        unset($namesToSort[$currentName]);

        // Before adding the module itself to the path, add all predecessors.
        // Do so recursively, then we make sure that each module is visited
        // in the path before any of its successors.
        foreach ($this->edges[$currentName] as $predecessor => $_) {
            // The module was already processed. Either the module is on the
            // path already, then we're good. Otherwise, we have a cycle.
            // However, addEdge() guarantees that the graph is cycle-free.
            if (isset($namesToSort[$predecessor])) {
                $this->sortModulesDFS($predecessor, $namesToSort, $output);
            }
        }

        $output[] = $currentName;
    }
}
