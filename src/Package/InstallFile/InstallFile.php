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
 * Contains metadata of the installed packages.
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
     * @var PackageMetadata[]
     */
    private $metadata = array();

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
     * Lists all package metadata.
     *
     * @return PackageMetadata[] The package metadata.
     */
    public function listPackageMetadata()
    {
        // The install paths as array keys are for internal use only
        return array_values($this->metadata);
    }

    /**
     * Clears the package metadata.
     */
    public function clearPackageMetadata()
    {
        $this->metadata = array();
    }

    /**
     * Adds package metadata.
     *
     * @param PackageMetadata $metadata The package metadata.
     */
    public function addPackageMetadata(PackageMetadata $metadata)
    {
        $this->metadata[$metadata->getInstallPath()] = $metadata;
    }

    /**
     * Removes the package metadata with a given install path.
     *
     * @param string $installPath The install path of the package.
     */
    public function removePackageMetadata($installPath)
    {
        unset($this->metadata[$installPath]);
    }

    /**
     * Returns the package metadata with a given install path.
     *
     * @param string $installPath The install path of the package.
     *
     * @return PackageMetadata The package metadata.
     *
     * @throws NoSuchPackageException If no package is installed at that path.
     */
    public function getPackageMetadata($installPath)
    {
        if (!isset($this->metadata[$installPath])) {
            throw new NoSuchPackageException(sprintf(
                'Could not get package metadata: No package is installed at %s.',
                $installPath
            ));
        }

        return $this->metadata[$installPath];
    }

    /**
     * Returns whether package metadata with a given install path exists.
     *
     * @param string $installPath The install path of the package.
     *
     * @return bool Whether package metadata with that path exists.
     */
    public function hasPackageMetadata($installPath)
    {
        return isset($this->metadata[$installPath]);
    }
}
