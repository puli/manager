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
     * @var string
     */
    private $packageName1;

    /**
     * @var string
     */
    private $packageName2;

    /**
     * Creates the conflict.
     *
     * @param string $conflictingToken The claimed token that caused the
     *                                 conflict.
     * @param string $packageName1     The name of the first package claiming
     *                                 the token.
     * @param string $packageName2     The name of the second package claiming
     *                                 the token.
     */
    public function __construct($conflictingToken, $packageName1, $packageName2)
    {
        $this->conflictingToken = $conflictingToken;
        $this->packageName1 = $packageName1;
        $this->packageName2 = $packageName2;
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
     * Returns the name of the first package causing the conflict.
     *
     * @return string The name of the first conflicting package.
     */
    public function getPackageName1()
    {
        return $this->packageName1;
    }

    /**
     * Returns the name of the second package causing the conflict.
     *
     * @return string The name of the second conflicting package.
     */
    public function getPackageName2()
    {
        return $this->packageName2;
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
        return $packageName === $this->packageName1 || $packageName === $this->packageName2;
    }

    /**
     * Returns the opposing package name in the conflict.
     *
     * @param string $packageName The name of the opposing package.
     *
     * @return string|null Returns the name of the opposing package or `null`
     *                     if the given package name is not involved in the
     *                     conflict.
     */
    public function getOpponent($packageName)
    {
        if ($packageName === $this->packageName1) {
            return $this->packageName2;
        }

        if ($packageName === $this->packageName2) {
            return $this->packageName1;
        }

        return null;
    }
}
