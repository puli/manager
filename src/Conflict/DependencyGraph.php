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
 * $graph = new DependencyGraph();
 * $graph->addModuleName('acme/core');
 * $graph->addModuleName('acme/blog');
 * $graph->addModuleName('acme/blog-extension1');
 * $graph->addModuleName('acme/blog-extension2');
 * $graph->addDependency('acme/blog', 'acme/core');
 * $graph->addDependency('acme/blog-extension1', 'acme/blog');
 * $graph->addDependency('acme/blog-extension2', 'acme/blog');
 * $graph->addDependency('acme/blog-extension2', 'acme/blog-extension1');
 * ```
 *
 * You can use {@link getPath()} and {@link hasPath()} to check whether a path
 * exists from one module to the other:
 *
 * ```php
 * // ...
 *
 * $graph->hasPath('acme/blog-extension1', 'acme/blog');
 * // => true
 *
 * $graph->hasPath('acme/blog-extension2', 'acme/blog-extension1');
 * // => false
 *
 * $graph->getPath('acme/blog-extension2', 'acme/core');
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
class DependencyGraph
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
     * The first dimension stores the module names, the second dimension the
     * names of the dependencies.
     *
     * @var array
     */
    private $dependencies = array();

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

            foreach ($module->getModuleFile()->getDependencies() as $dependency) {
                if ($graph->hasModuleName($dependency)) {
                    $graph->addDependency($module->getName(), $dependency);
                }
            }
        }

        // Do we have a root module?
        if (null === $modules->getRootModule()) {
            return $graph;
        }

        // Make sure we have numeric, ascending keys here
        $moduleOrder = array_values($modules->getRootModule()->getModuleFile()->getModuleOrder());

        // Each module overrides the previous one in the list
        for ($i = 1, $l = count($moduleOrder); $i < $l; ++$i) {
            $dependency = $moduleOrder[$i - 1];
            $moduleName = $moduleOrder[$i];

            if ($graph->hasModuleName($dependency)) {
                $graph->addDependency($moduleName, $dependency);
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
        $this->dependencies[$moduleName] = array();
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
     * Returns all module names in the graph.
     *
     * @return array All module names in the graph.
     */
    public function getModuleNames()
    {
        return $this->moduleNames;
    }

    /**
     * Returns the sorted module names.
     *
     * The names are sorted such that if a module m1 depends on a module m2,
     * then m2 comes before m1 in the sorted set.
     *
     * If module names are passed, only those module names are sorted. Otherwise
     * all module names are sorted.
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
     * Adds a dependency from one to another module.
     *
     * @param string $moduleName The module name.
     * @param string $dependency The name of the dependency.
     */
    public function addDependency($moduleName, $dependency)
    {
        if (!isset($this->moduleNames[$dependency])) {
            throw new RuntimeException(sprintf(
                'The module "%s" does not exist in the graph.',
                $dependency
            ));
        }

        if (!isset($this->moduleNames[$moduleName])) {
            throw new RuntimeException(sprintf(
                'The module "%s" does not exist in the graph.',
                $moduleName
            ));
        }

        if (null !== ($path = $this->getPath($dependency, $moduleName))) {
            $last = array_pop($path);

            throw new CyclicDependencyException(sprintf(
                'A cyclic dependency was discovered between the modules "%s" '.
                'and "%s". Please check the "override" keys defined in these '.
                'modules.',
                implode('", "', $path),
                $last
            ));
        }

        $this->dependencies[$moduleName][$dependency] = true;
    }

    /**
     * Removes a dependency from one to another module.
     *
     * @param string $moduleName The module name.
     * @param string $dependency The name of the dependency.
     */
    public function removeDependency($moduleName, $dependency)
    {
        unset($this->dependencies[$moduleName][$dependency]);
    }

    /**
     * Returns whether a module directly depends on another module.
     *
     * @param string $moduleName The module name.
     * @param string $dependency The name of the dependency.
     * @param bool   $recursive  Whether to take recursive dependencies into
     *                           account.
     *
     * @return bool Whether an edge exists from the origin to the target module.
     */
    public function hasDependency($moduleName, $dependency, $recursive = true)
    {
        if ($recursive) {
            return $this->hasPath($moduleName, $dependency);
        }

        return isset($this->dependencies[$moduleName][$dependency]);
    }

    /**
     * Returns whether a path exists from a module to a dependency.
     *
     * @param string $moduleName The module name.
     * @param string $dependency The name of the dependency.
     *
     * @return bool Whether a path exists from the origin to the target module.
     */
    public function hasPath($moduleName, $dependency)
    {
        // does not exist in the graph
        if (!isset($this->dependencies[$moduleName])) {
            return false;
        }

        // adjacent node
        if (isset($this->dependencies[$moduleName][$dependency])) {
            return true;
        }

        // DFS
        foreach ($this->dependencies[$moduleName] as $predecessor => $_) {
            if ($this->hasPath($predecessor, $dependency)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the path from a module name to a dependency.
     *
     * @param string $moduleName The module name.
     * @param string $dependency The name of the dependency.
     *
     * @return null|string[] The sorted module names on the path or `null` if no
     *                       path was found.
     */
    public function getPath($moduleName, $dependency)
    {
        if ($this->getPathDFS($moduleName, $dependency, $reversePath)) {
            return array_reverse($reversePath);
        }

        return null;
    }

    /**
     * Finds a path between modules using Depth-First Search.
     *
     * @param string $moduleName  The end module name.
     * @param string $dependency  The start module name.
     * @param array  $reversePath The path in reverse order.
     *
     * @return bool Whether a path was found.
     */
    private function getPathDFS($moduleName, $dependency, &$reversePath = array())
    {
        // does not exist in the graph
        if (!isset($this->dependencies[$moduleName])) {
            return false;
        }

        $reversePath[] = $moduleName;

        // adjacent node
        if (isset($this->dependencies[$moduleName][$dependency])) {
            $reversePath[] = $dependency;

            return true;
        }

        // DFS
        foreach ($this->dependencies[$moduleName] as $predecessor => $_) {
            if ($this->getPathDFS($predecessor, $dependency, $reversePath)) {
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
        foreach ($this->dependencies[$currentName] as $predecessor => $_) {
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
