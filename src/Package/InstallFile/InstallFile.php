<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\InstallFile;

use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\NoSuchPackageException;

/**
 * Contains information about all installed packages.
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
     * @var InstallInfo[]
     */
    private $installInfos = array();

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
     * Returns the install infos of all packages.
     *
     * @return InstallInfo[] The install infos.
     */
    public function getInstallInfos()
    {
        // The install paths as array keys are for internal use only
        return array_values($this->installInfos);
    }

    /**
     * Sets the install infos of all installed packages.
     *
     * @param InstallInfo[] The install infos.
     */
    public function setInstallInfos(array $installInfos)
    {
        $this->installInfos = array();

        foreach ($installInfos as $installInfo) {
            $this->addInstallInfo($installInfo);
        }
    }

    /**
     * Adds install info for a package.
     *
     * @param InstallInfo $installInfo The install info.
     */
    public function addInstallInfo(InstallInfo $installInfo)
    {
        $this->installInfos[$installInfo->getInstallPath()] = $installInfo;
    }

    /**
     * Removes the install info of a package.
     *
     * @param string $installPath The install path of the package.
     */
    public function removeInstallInfo($installPath)
    {
        unset($this->installInfos[$installPath]);
    }

    /**
     * Returns the install info of a package.
     *
     * @param string $installPath The install path of the package.
     *
     * @return InstallInfo The install info.
     *
     * @throws NoSuchPackageException If no package is installed at that path.
     */
    public function getInstallInfo($installPath)
    {
        if (!isset($this->installInfos[$installPath])) {
            throw new NoSuchPackageException(sprintf(
                'Could not get install info: No package is installed at %s.',
                $installPath
            ));
        }

        return $this->installInfos[$installPath];
    }

    /**
     * Returns whether an install info with a given install path exists.
     *
     * @param string $installPath The install path of the package.
     *
     * @return bool Whether install info with that path exists.
     */
    public function hasInstallInfo($installPath)
    {
        return isset($this->installInfos[$installPath]);
    }
}
