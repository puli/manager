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
 * A conflict between two packages claiming the same token.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    PackageConflictDetector
 */
class PackageConflict
{
    /**
     * @var string
     */
    private $conflictingToken;

    /**
     * @var string[]
     */
    private $packageNames;

    /**
     * Creates the conflict.
     *
     * @param string   $conflictingToken The token that caused the conflict.
     * @param string[] $packageNames     The names of the packages claiming the
     *                                   token.
     */
    public function __construct($conflictingToken, array $packageNames)
    {
        sort($packageNames);

        $this->conflictingToken = $conflictingToken;
        $this->packageNames = array();

        foreach ($packageNames as $packageName) {
            $this->packageNames[$packageName] = true;
        }
    }

    /**
     * Returns the conflicting repository path.
     *
     * @return string The conflicting repository path.
     */
    public function getConflictingToken()
    {
        return $this->conflictingToken;
    }

    /**
     * Returns the names of the packages causing the conflict.
     *
     * @return string[] The name of the first conflicting package.
     */
    public function getPackageNames()
    {
        return array_keys($this->packageNames);
    }

    /**
     * Returns whether the conflict involves a given package name.
     *
     * @param string $packageName A package name.
     *
     * @return bool Returns `true` if the package caused the conflict.
     */
    public function involvesPackage($packageName)
    {
        return isset($this->packageNames[$packageName]);
    }

    /**
     * Returns the opposing package names in the conflict.
     *
     * @param string $packageName The name of a package.
     *
     * @return string[] Returns the names of the opposing packages or an empty
     *                  array if the package is not involved in the conflict.
     */
    public function getOpponents($packageName)
    {
        if (!isset($this->packageNames[$packageName])) {
            return array();
        }

        $opponents = $this->packageNames;

        unset($opponents[$packageName]);

        return array_keys($opponents);
    }
}
