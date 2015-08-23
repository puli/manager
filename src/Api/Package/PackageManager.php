<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\InvalidConfigException;
use Webmozart\Expression\Expression;

/**
 * Manages the package repository of a Puli project.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageManager
{
    /**
     * Returns the manager's context.
     *
     * @return ProjectContext The project context.
     */
    public function getContext();

    /**
     * Installs the package at the given path in the repository.
     *
     * @param string      $installPath   The path to the package.
     * @param string|null $name          The package name or `null` if the name
     *                                   should be read from the package's
     *                                   puli.json.
     * @param string      $installerName The name of the installer.
     * @param string      $env           The environment to install the package
     *                                   in.
     *
     * @throws InvalidConfigException If the package is not configured correctly.
     * @throws NameConflictException  If the package has the same name as another
     *                                loaded package.
     */
    public function installPackage($installPath, $name = null, $installerName = InstallInfo::DEFAULT_INSTALLER_NAME, $env = Environment::PROD);

    /**
     * Renames the package with the given name.
     *
     * @param string $name    The package name.
     * @param string $newName The new package name.
     *
     * @throws NoSuchPackageException If the package was not found.
     * @throws NameConflictException  If a package with the new name exists
     *                                already.
     */
    public function renamePackage($name, $newName);

    /**
     * Removes the package with the given name.
     *
     * @param string $name The package name.
     */
    public function removePackage($name);

    /**
     * Removes all packages matching the given expression.
     *
     * If no matching packages are found, this method does nothing.
     *
     * @param Expression $expr The search criteria.
     */
    public function removePackages(Expression $expr);

    /**
     * Removes all packages.
     *
     * If matching packages are found, this method does nothing.
     */
    public function clearPackages();

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
     * @return PackageCollection The installed packages.
     */
    public function getPackages();

    /**
     * Returns all packages matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return PackageCollection The packages matching the expression.
     */
    public function findPackages(Expression $expr);

    /**
     * Returns whether the manager has the package with the given name.
     *
     * @param string $name The package name.
     *
     * @return bool Whether the manager has a package with that name.
     */
    public function hasPackage($name);

    /**
     * Returns whether the manager has any packages.
     *
     * You can optionally pass an expression to check whether the manager has
     * packages matching the expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has packages and `false`
     *              otherwise. If an expression was passed, this method only
     *              returns `true` if the manager has packages matching the
     *              expression.
     */
    public function hasPackages(Expression $expr = null);
}
