<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installer;

use Webmozart\Expression\Expression;

/**
 * Manages the installers used to install resources on asset servers.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface InstallerManager
{
    /**
     * Adds an installer descriptor.
     *
     * @param InstallerDescriptor $descriptor The installer descriptor.
     */
    public function addRootInstallerDescriptor(InstallerDescriptor $descriptor);

    /**
     * Removes the installer descriptor with the given name.
     *
     * If the installer descriptor does not exist, this method does nothing.
     *
     * @param string $name The installer name.
     */
    public function removeRootInstallerDescriptor($name);

    /**
     * Removes all installer descriptors matching the given expression.
     *
     * @param Expression $expr The search criteria.
     */
    public function removeRootInstallerDescriptors(Expression $expr);

    /**
     * Removes all installer descriptors.
     */
    public function clearRootInstallerDescriptors();

    /**
     * Returns the installer descriptor with the given name from the root
     * package.
     *
     * @param string $name The installer name.
     *
     * @return InstallerDescriptor The installer descriptor.
     */
    public function getRootInstallerDescriptor($name);

    /**
     * Returns all installer descriptors in the root package.
     *
     * @return InstallerDescriptor[] The installer descriptors.
     */
    public function getRootInstallerDescriptors();

    /**
     * Returns all installer descriptors in the root package that match the
     * given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return InstallerDescriptor[] The installer descriptors that match the
     *                               expression.
     */
    public function findRootInstallerDescriptors(Expression $expr);

    /**
     * Returns whether the installer descriptor with the given name exists in
     * the root package.
     *
     * @param string $name The installer name.
     *
     * @return bool Returns `true` if the installer with the given name
     *              exists and `false` otherwise.
     */
    public function hasRootInstallerDescriptor($name);

    /**
     * Returns whether the root package contains any installer descriptors.
     *
     * You can optionally pass an expression to check whether the root package
     * has installers matching that expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager contains installers and
     *              `false` otherwise.
     */
    public function hasRootInstallerDescriptors(Expression $expr = null);

    /**
     * Returns the installer descriptor with the given name.
     *
     * @param string $name The installer name.
     *
     * @return InstallerDescriptor The installer descriptor.
     */
    public function getInstallerDescriptor($name);

    /**
     * Returns all registered installer descriptors.
     *
     * @return InstallerDescriptor[] The installer descriptors.
     */
    public function getInstallerDescriptors();

    /**
     * Returns all installer descriptors matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return InstallerDescriptor[] The installer descriptors that match the
     *                               expression.
     */
    public function findInstallerDescriptors(Expression $expr);

    /**
     * Returns whether the installer descriptor with the given name exists.
     *
     * @param string $name The installer name.
     *
     * @return bool Returns `true` if the installer with the given name
     *              exists and `false` otherwise.
     */
    public function hasInstallerDescriptor($name);

    /**
     * Returns whether the manager contains any installer descriptors.
     *
     * You can optionally pass an expression to check whether the manager has
     * installers matching that expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager contains installers and
     *              `false` otherwise.
     */
    public function hasInstallerDescriptors(Expression $expr = null);
}
