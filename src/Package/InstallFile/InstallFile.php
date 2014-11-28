<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\InstallFile;

use Puli\RepositoryManager\Package\NoSuchPackageException;

/**
 * Contains descriptors of the installed packages.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallFile
{
    /**
     * @var string|null
     */
    private $path;

    /**
     * @var PackageDescriptor[]
     */
    private $packageDescriptors = array();

    /**
     * Creates a new install file.
     *
     * @param string|null $path The path where the install file is stored or
     *                          `null` if it is not stored on the file system.
     *
     * @throws \InvalidArgumentException If the path is not a string or empty.
     */
    public function __construct($path = null)
    {
        if (!is_string($path) && null !== $path) {
            throw new \InvalidArgumentException(sprintf(
                'The path to the install file should be a string or null. Got: %s',
                is_object($path) ? get_class($path) : gettype($path)
            ));
        }

        if ('' === $path) {
            throw new \InvalidArgumentException('The path to the install file should not be empty.');
        }

        $this->path = $path;
    }

    /**
     * Returns the file system path of the install file.
     *
     * @return string|null The path or `null` if the install file is not stored
     *                     on the file system.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the package descriptors.
     *
     * @return PackageDescriptor[] The package descriptors.
     */
    public function getPackageDescriptors()
    {
        // The install paths as array keys are for internal use only
        return array_values($this->packageDescriptors);
    }

    /**
     * Sets the package descriptors.
     *
     * @param PackageDescriptor[] $descriptors The package descriptors.
     */
    public function setPackageDescriptors(array $descriptors)
    {
        $this->packageDescriptors = array();

        foreach ($descriptors as $descriptor) {
            $this->addPackageDescriptor($descriptor);
        }
    }

    /**
     * Adds a package descriptor.
     *
     * @param PackageDescriptor $descriptor The package descriptor.
     */
    public function addPackageDescriptor(PackageDescriptor $descriptor)
    {
        $this->packageDescriptors[$descriptor->getInstallPath()] = $descriptor;
    }

    /**
     * Removes the package descriptor with a given install path.
     *
     * @param string $installPath The install path of the package.
     */
    public function removePackageDescriptor($installPath)
    {
        unset($this->packageDescriptors[$installPath]);
    }

    /**
     * Returns the package descriptor with a given install path.
     *
     * @param string $installPath The install path of the package.
     *
     * @return PackageDescriptor The package descriptor.
     *
     * @throws NoSuchPackageException If no package is installed at that path.
     */
    public function getPackageDescriptor($installPath)
    {
        if (!isset($this->packageDescriptors[$installPath])) {
            throw new NoSuchPackageException(sprintf(
                'Could not get package descriptor: No package is installed at %s.',
                $installPath
            ));
        }

        return $this->packageDescriptors[$installPath];
    }

    /**
     * Returns whether a package descriptor with a given install path exists.
     *
     * @param string $installPath The install path of the package.
     *
     * @return bool Whether a package descriptor with that path exists.
     */
    public function hasPackageDescriptor($installPath)
    {
        return isset($this->packageDescriptors[$installPath]);
    }
}
