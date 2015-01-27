<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Package;

use Puli\RepositoryManager\Api\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Api\InvalidConfigException;

/**
 * Manages the package repository of a Puli project.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageManager
{
    /**
     * Installs the package at the given path in the repository.
     *
     * @param string      $installPath   The path to the package.
     * @param string|null $name          The package name or `null` if the name
     *                                   should be read from the package's
     *                                   puli.json.
     * @param string      $installerName The name of the installer.
     *
     * @throws InvalidConfigException If the package is not configured correctly.
     * @throws NameConflictException If the package has the same name as another
     *                               loaded package.
     */
    public function installPackage($installPath, $name = null, $installerName = InstallInfo::DEFAULT_INSTALLER_NAME);

    /**
     * Returns whether the package with the given path is installed.
     *
     * @param string $installPath The install path of the package.
     *
     * @return bool Whether that package is installed.
     */
    public function isPackageInstalled($installPath);

    /**
     * Removes the package with the given name.
     *
     * @param string $name The package name.
     */
    public function removePackage($name);

    /**
     * Returns whether the manager has the package with the given name.
     *
     * @param string $name The package name.
     *
     * @return bool Whether the manager has a package with that name.
     */
    public function hasPackage($name);

    /**
     * Returns a package by name.
     *
     * @param string $name The package name.
     *
     * @return Package The package.
     *
     * @throws NoSuchPackageException If the package was not found.
     */
    public function getPackage($name);

    /**
     * Returns the root package.
     *
     * @return RootPackage The root package.
     */
    public function getRootPackage();

    /**
     * Returns all installed packages.
     *
     * @param int $state The state of the packages to return.
     *
     * @return PackageCollection The installed packages.
     */
    public function getPackages($state = null);

    /**
     * Returns all packages installed by the given installer.
     *
     * @param string $installerName The installer name.
     * @param int    $state     The state of the packages to return.
     *
     * @return PackageCollection The packages.
     */
    public function getPackagesByInstaller($installerName, $state = null);

    /**
     * Returns the manager's environment.
     *
     * @return ProjectEnvironment The project environment.
     */
    public function getEnvironment();
}
