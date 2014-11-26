<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Repository;

use Puli\PackageManager\Package\Package;
use Puli\PackageManager\Package\RootPackage;

/**
 * A repository for Puli packages.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRepository
{
    /**
     * @var Package
     */
    private $rootPackage;

    /**
     * @var Package
     */
    private $packages = array();

    /**
     * Returns the packages in the repository.
     *
     * @return Package[] The packages in the repository.
     */
    public function getPackages()
    {
        return $this->packages;
    }

    /**
     * Adds a package to the repository.
     *
     * @param Package $package The added package.
     */
    public function addPackage(Package $package)
    {
        $this->packages[$package->getName()] = $package;

        if ($package instanceof RootPackage) {
            $this->rootPackage = $package;
        }
    }

    /**
     * Returns the package with the given name.
     *
     * @param string $name The package name.
     *
     * @return Package The package with the passed name.
     *
     * @throws NoSuchPackageException If the package was not found.
     */
    public function getPackage($name)
    {
        if (!isset($this->packages[$name])) {
            throw new NoSuchPackageException(sprintf(
                'The package "%s" was not found.',
                $name
            ));
        }

        return $this->packages[$name];
    }

    /**
     * Returns whether a package with the given name exists.
     *
     * @param string $name The package name.
     *
     * @return bool Whether a package with this name exists.
     */
    public function containsPackage($name)
    {
        return isset($this->packages[$name]);
    }

    /**
     * Returns the root package.
     *
     * If the repository contains no root package, `null` is returned.
     *
     * @return RootPackage|null The root package or `null` if none exists.
     */
    public function getRootPackage()
    {
        return $this->rootPackage;
    }
}
