<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package;

use Puli\RepositoryManager\Package\PackageFile\PackageFile;

/**
 * A configured package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Package
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var PackageFile
     */
    private $packageFile;

    /**
     * Creates a new package.
     *
     * @param PackageFile $packageFile The package file.
     * @param string      $installPath The install path of the package.
     */
    public function __construct(PackageFile $packageFile, $installPath)
    {
        $this->name = $packageFile->getPackageName();
        $this->packageFile = $packageFile;
        $this->installPath = $installPath;
    }

    /**
     * Returns the name of the package.
     *
     * @return string The name of the package.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the path at which the package is installed.
     *
     * @return string The install path of the package.
     */
    public function getInstallPath()
    {
        return $this->installPath;
    }

    /**
     * Returns the package file of the package.
     *
     * @return PackageFile The package file.
     */
    public function getPackageFile()
    {
        return $this->packageFile;
    }

    /**
     * Sets the package name.
     *
     * @param string $name The package name.
     */
    protected function setName($name)
    {
        $this->name = $name;
    }
}
