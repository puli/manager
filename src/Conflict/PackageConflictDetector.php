<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Conflict;

/**
 * Detects configuration conflicts between packages.
 *
 * Packages may claim "tokens" for themselves. A token, in that sense, can be
 * any integer or string. If packages claim the same token, a conflict is
 * raised:
 *
 * ```php
 * use Puli\RepositoryManager\Conflict\PackageConflictDetector;
 *
 * $detector = new PackageConflictDetector();
 * $detector->claim('/app/config', 'package1');
 * $detector->claim('/app/views', 'package2');
 *
 * $conflict = $detector->detectConflict();
 * // => null
 *
 * $detector->claim('/app/config', 'package2');
 *
 * $conflict = $detector->detectConflict();
 * // => PackageConflict
 * ```
 *
 * You can resolve conflicts by passing an {@link OverrideGraph} to the
 * detector. The override graph has package names as nodes. When the conflict
 * graph contains an edge from package A to package B, then package A is
 * considered to be overridden by package B. Claims for the same resources will
 * not result in conflicts for these packages:
 *
 * ```php
 * use Puli\RepositoryManager\Conflict\OverrideGraph;
 * use Puli\RepositoryManager\Conflict\PackageConflictDetector;
 *
 * $graph = new OverrideGraph();
 * $graph->addPackageName('package1');
 * $graph->addPackageName('package2');
 *
 * // package1 is overridden by package2
 * $graph->addEdge('package1', 'package2');
 *
 * $detector = new PackageConflictDetector($graph);
 * $detector->claim('/app/config', 'package1');
 * $detector->claim('/app/config', 'package2');
 *
 * // The conflict has been resolved
 * $conflict = $detector->detectConflict();
 * // => null
 * ```
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConflictDetector
{
    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var bool[][]
     */
    private $tokens = array();

    /**
     * @var bool[]
     */
    private $uncheckedTokens = array();

    /**
     * Creates a new conflict detector.
     *
     * @param OverrideGraph $overrideGraph The graph indicating which package is
     *                                     overridden by which other package.
     */
    public function __construct(OverrideGraph $overrideGraph = null)
    {
        $this->overrideGraph = $overrideGraph ?: new OverrideGraph();
    }

    /**
     * Claims a token for a package.
     *
     * @param int|string $token       The claimed token. Can be any integer or
     *                                string.
     * @param string     $packageName The package name.
     */
    public function claim($token, $packageName)
    {
        if (!isset($this->tokens[$token])) {
            $this->tokens[$token] = array();
        }

        $this->tokens[$token][$packageName] = true;

        // Remember that the token needs to be checked during conflict detection
        $this->uncheckedTokens[$token] = true;
    }

    /**
     * Releases a package's claim for a token.
     *
     * @param int|string $token       The claimed token. Can be any integer or
     *                                string.
     * @param string     $packageName The package name.
     */
    public function release($token, $packageName)
    {
        unset($this->tokens[$token][$packageName]);
    }

    /**
     * Checks the claimed tokens for conflicts.
     *
     * A conflict is returned for every token that is claimed by two packages
     * that have no edge in the override graph. In other words, if two packages
     * A and B claim the same token, an edge must exist from A to B (A is
     * overridden by B) or from B to A (B is overridden by A). Otherwise a
     * conflict is returned.
     *
     * @return PackageConflict Returns the detected conflict or `null` if no
     *                         conflict was found.
     */
    public function detectConflict()
    {
        foreach ($this->uncheckedTokens as $token => $true) {
            // Claim was released
            if (!isset($this->tokens[$token])) {
                unset($this->uncheckedTokens[$token]);

                continue;
            }

            $packageNames = array_keys($this->tokens[$token]);

            // Token claimed by only one package
            if (1 === count($packageNames)) {
                unset($this->uncheckedTokens[$token]);

                continue;
            }

            $sortedNames = $this->overrideGraph->getSortedPackageNames($packageNames);

            // An edge must exist between each package pair in the sorted set,
            // otherwise the dependencies are not sufficiently defined
            for ($i = 1, $l = count($sortedNames); $i < $l; ++$i) {
                if (!$this->overrideGraph->hasEdge($sortedNames[$i - 1], $sortedNames[$i])) {
                    return new PackageConflict($token, $sortedNames[$i - 1], $sortedNames[$i]);
                }
            }

            // Mark token as checked
            unset($this->uncheckedTokens[$token]);
        }

        return null;
    }

}
