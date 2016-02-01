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

/**
 * Detects configuration conflicts between modules.
 *
 * Modules may claim "tokens" for themselves. A token, in that sense, can be
 * any integer or string. If modules claim the same token, a conflict is
 * raised:
 *
 * ```php
 * use Puli\Manager\Conflict\ModuleConflictDetector;
 *
 * $detector = new ModuleConflictDetector();
 * $detector->claim('/app/config', 'module1');
 * $detector->claim('/app/views', 'module2');
 *
 * $conflicts = $detector->detectConflicts(array('/app/config', '/app/views'));
 * // => array()
 *
 * $detector->claim('/app/config', 'module2');
 *
 * $conflicts = $detector->detectConflicts(array('/app/config', '/app/views'));
 * // => array(ModuleConflict)
 * ```
 *
 * You can resolve conflicts by passing an {@link OverrideGraph} to the
 * detector. The override graph has module names as nodes. When the conflict
 * graph contains an edge from module A to module B, then module A is
 * considered to be overridden by module B. Claims for the same resources will
 * not result in conflicts for these modules:
 *
 * ```php
 * use Puli\Manager\Conflict\OverrideGraph;
 * use Puli\Manager\Conflict\ModuleConflictDetector;
 *
 * $graph = new OverrideGraph();
 * $graph->addModuleName('module1');
 * $graph->addModuleName('module2');
 *
 * // module1 is overridden by module2
 * $graph->addEdge('module1', 'module2');
 *
 * $detector = new ModuleConflictDetector($graph);
 * $detector->claim('/app/config', 'module1');
 * $detector->claim('/app/config', 'module2');
 *
 * // The conflict has been resolved
 * $conflict s= $detector->detectConflict(array('/app/config'));
 * // => array()
 * ```
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleConflictDetector
{
    /**
     * @var DependencyGraph
     */
    private $dependencyGraph;

    /**
     * @var bool[][]
     */
    private $tokens = array();

    /**
     * Creates a new conflict detector.
     *
     * @param DependencyGraph|null $dependencyGraph The graph indicating which
     *                                              module depends on which
     *                                              other module.
     */
    public function __construct(DependencyGraph $dependencyGraph = null)
    {
        $this->dependencyGraph = $dependencyGraph ?: new DependencyGraph();
    }

    /**
     * Claims a token for a module.
     *
     * @param int|string $token      The claimed token. Can be any integer or
     *                               string.
     * @param string     $moduleName The module name.
     */
    public function claim($token, $moduleName)
    {
        if (!isset($this->tokens[$token])) {
            $this->tokens[$token] = array();
        }

        $this->tokens[$token][$moduleName] = true;
    }

    /**
     * Releases a module's claim for a token.
     *
     * @param int|string $token      The claimed token. Can be any integer or
     *                               string.
     * @param string     $moduleName The module name.
     */
    public function release($token, $moduleName)
    {
        unset($this->tokens[$token][$moduleName]);
    }

    /**
     * Checks the passed tokens for conflicts.
     *
     * If no tokens are passed, all tokens are checked.
     *
     * A conflict is returned for every token that is claimed by two modules
     * that are not connected by an edge in the override graph. In other words,
     * if two modules A and B claim the same token, an edge must exist from A
     * to B (A is overridden by B) or from B to A (B is overridden by A).
     * Otherwise a conflict is returned.
     *
     * @param int[]|string[]|null $tokens The tokens to check. If `null`, all
     *                                    claimed tokens are checked for
     *                                    conflicts. You are advised to pass
     *                                    tokens if possible to improve the
     *                                    performance of the conflict detection.
     *
     * @return ModuleConflict[] The detected conflicts.
     */
    public function detectConflicts(array $tokens = null)
    {
        $tokens = null === $tokens ? array_keys($this->tokens) : $tokens;
        $conflicts = array();

        foreach ($tokens as $token) {
            // Claim was released
            if (!isset($this->tokens[$token])) {
                continue;
            }

            $moduleNames = array_keys($this->tokens[$token]);

            // Token claimed by only one module
            if (1 === count($moduleNames)) {
                continue;
            }

            $sortedNames = $this->dependencyGraph->getSortedModuleNames($moduleNames);
            $conflictingNames = array();

            // An edge must exist between each module pair in the sorted set,
            // otherwise the dependencies are not sufficiently defined
            for ($i = 1, $l = count($sortedNames); $i < $l; ++$i) {
                // Exclude recursive dependencies
                if (!$this->dependencyGraph->hasDependency($sortedNames[$i], $sortedNames[$i - 1], false)) {
                    $conflictingNames[$sortedNames[$i - 1]] = true;
                    $conflictingNames[$sortedNames[$i]] = true;
                }
            }

            if (count($conflictingNames) > 0) {
                $conflicts[] = new ModuleConflict($token, array_keys($conflictingNames));
            }
        }

        return $conflicts;
    }
}
