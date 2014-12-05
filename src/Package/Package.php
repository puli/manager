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

use Puli\RepositoryManager\Package\InstallFile\PackageMetadata;
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
     * @var PackageMetadata
     */
    private $metadata;

    /**
     * Creates a new package.
     *
     * @param PackageFile     $packageFile The package file.
     * @param string          $installPath The absolute install path.
     * @param PackageMetadata $metadata    The package metadata.
     */
    public function __construct(PackageFile $packageFile, $installPath, PackageMetadata $metadata = null)
    {
        // If a package name was set during installation, that name wins over
        // the predefined name in the puli.json file (if any)
        $this->name = $metadata && null !== $metadata->getName()
            ? $metadata->getName()
            : $packageFile->getPackageName();

        $this->packageFile = $packageFile;

        // The path is stored both here and in the metadata. While the metadata
        // contains the path as it is stored in the install file (i.e. relative
        // or absolute), the install path of the package is always an absolute
        // path.
        $this->installPath = $installPath;
        $this->metadata = $metadata;
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
     * Returns the package's metadata.
     *
     * @return PackageMetadata The metadata.
     */
    public function getMetadata()
    {
        return $this->metadata;
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
