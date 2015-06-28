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

use InvalidArgumentException;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Assert\Assert;
use Rhumsaa\Uuid\Uuid;

/**
 * Contains information about a package installation.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallInfo
{
    /**
     * The default installer of packages.
     */
    const DEFAULT_INSTALLER_NAME = 'user';

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var string|null
     */
    private $packageName;

    /**
     * @var bool
     */
    private $isDev;


    /**
     * @var string
     */
    private $installerName = self::DEFAULT_INSTALLER_NAME;

    /**
     * @var BindingDescriptor[]
     */
    private $enabledBindingUuids = array();

    /**
     * @var BindingDescriptor[]
     */
    private $disabledBindingUuids = array();

    /**
     * Creates a new install info.
     *
     * @param string $packageName The package name. Must be a non-empty string.
     * @param string $installPath The path where the package is installed.
     *                            If a relative path is given, the path is
     *                            assumed to be relative to the install path
     *                            of the root package.
     * @param bool   $isDev       The true or false value that defines a dev
     *                            package.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($packageName, $installPath, $isDev = false)
    {
        Assert::packageName($packageName);
        Assert::systemPath($installPath);

        $this->packageName = $packageName;
        $this->installPath = $installPath;
        $this->isDev = $isDev;
    }

    /**
     * Returns the path where the package is installed.
     *
     * @return string The path where the package is installed. The path is
     *                either absolute or relative to the install path of the
     *                root package.
     */
    public function getInstallPath()
    {
        return $this->installPath;
    }

    /**
     * Returns the package name.
     *
     * @return null|string Returns the package name or `null` if the name is
     *                     read from the package's puli.json file.
     */
    public function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * Returns the name of the installer of the package.
     *
     * @return string The package's installer name.
     */
    public function getInstallerName()
    {
        return $this->installerName;
    }

    /**
     * Sets the name of the installer of the package.
     *
     * @param string $installerName The package's installer name.
     */
    public function setInstallerName($installerName)
    {
        $this->installerName = $installerName;
    }

    /**
     * Returns the enabled resource bindings of the package.
     *
     * @return Uuid[] The UUIDs of the enabled resource bindings.
     */
    public function getEnabledBindingUuids()
    {
        return array_values($this->enabledBindingUuids);
    }

    /**
     * Adds an enabled resource binding for the package.
     *
     * @param Uuid $uuid The UUID of the enabled resource binding.
     */
    public function addEnabledBindingUuid(Uuid $uuid)
    {
        $this->enabledBindingUuids[$uuid->toString()] = $uuid;

        $this->removeDisabledBindingUuid($uuid);
    }

    /**
     * Removes an enabled resource binding.
     *
     * If the resource binding is not enabled, this method does nothing.
     *
     * @param Uuid $uuid The UUID of the resource binding to remove.
     */
    public function removeEnabledBindingUuid(Uuid $uuid)
    {
        unset($this->enabledBindingUuids[$uuid->toString()]);
    }

    /**
     * Returns whether the resource binding is enabled.
     *
     * @param Uuid $uuid The UUID of the resource binding.
     *
     * @return bool Whether the resource binding is enabled.
     */
    public function hasEnabledBindingUuid(Uuid $uuid)
    {
        return isset($this->enabledBindingUuids[$uuid->toString()]);
    }

    /**
     * Returns whether the install info contains enabled bindings.
     *
     * @return bool Whether any bindings are enabled.
     */
    public function hasEnabledBindingUuids()
    {
        return count($this->enabledBindingUuids) > 0;
    }

    /**
     * Returns the disabled resource bindings of the package.
     *
     * @return Uuid[] The UUIDs of the disabled resource bindings.
     */
    public function getDisabledBindingUuids()
    {
        return array_values($this->disabledBindingUuids);
    }

    /**
     * Adds a disabled resource binding for the package.
     *
     * @param Uuid $uuid The UUID of the disabled resource binding.
     */
    public function addDisabledBindingUuid(Uuid $uuid)
    {
        $this->disabledBindingUuids[$uuid->toString()] = $uuid;

        $this->removeEnabledBindingUuid($uuid);
    }

    /**
     * Removes a disabled resource binding.
     *
     * If the resource binding is not disabled, this method does nothing.
     *
     * @param Uuid $uuid The UUID of the resource binding to remove.
     */
    public function removeDisabledBindingUuid(Uuid $uuid)
    {
        unset($this->disabledBindingUuids[$uuid->toString()]);
    }

    /**
     * Returns whether the resource binding is disabled.
     *
     * @param Uuid $uuid The UUID of the resource binding.
     *
     * @return bool Whether the resource binding is disabled.
     */
    public function hasDisabledBindingUuid(Uuid $uuid)
    {
        return isset($this->disabledBindingUuids[$uuid->toString()]);
    }

    /**
     * Returns whether the install info contains disabled bindings.
     *
     * @return bool Whether any bindings are disabled.
     */
    public function hasDisabledBindingUuids()
    {
        return count($this->disabledBindingUuids) > 0;
    }

    /**
     * Returns whether the packages is a dev requirement or not.
     *
     * @return bool Whether the packages is a dev requirement or not.
     */
    public function isDev()
    {
        return $this->isDev;
    }
}
