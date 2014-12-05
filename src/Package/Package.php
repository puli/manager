<?php

/*
 * This file is part of the puli/repository-manager package.
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
     * @var PackageFile
     */
    private $packageFile;

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var InstallInfo
     */
    private $installInfo;

    /**
     * Creates a new package.
     *
     * @param PackageFile $packageFile The package file.
     * @param string      $installPath The absolute install path.
     * @param InstallInfo $installInfo The install info of this package.
     */
    public function __construct(PackageFile $packageFile, $installPath, InstallInfo $installInfo = null)
    {
        // If a package name was set during installation, that name wins over
        // the predefined name in the puli.json file (if any)
        $this->name = $installInfo && null !== $installInfo->getPackageName()
            ? $installInfo->getPackageName()
            : $packageFile->getPackageName();

        $this->packageFile = $packageFile;

        // The path is stored both here and in the install info. While the
        // install info contains the path as it is stored in the install file
        // (i.e. relative or absolute), the install path of the package is
        // always an absolute path.
        $this->installPath = $installPath;
        $this->installInfo = $installInfo;
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
     * @return string The absolute install path of the package.
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
     * Returns the package's install info.
     *
     * @return InstallInfo The install info.
     */
    public function getInstallInfo()
    {
        return $this->installInfo;
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
