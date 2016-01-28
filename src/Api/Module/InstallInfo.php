<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Module;

use InvalidArgumentException;
use Puli\Manager\Api\Environment;
use Puli\Manager\Assert\Assert;
use Rhumsaa\Uuid\Uuid;

/**
 * Contains information about a module installation.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallInfo
{
    /**
     * The default installer of modules.
     */
    const DEFAULT_INSTALLER_NAME = 'user';

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var string|null
     */
    private $moduleName;

    /**
     * @var string
     */
    private $env = Environment::PROD;

    /**
     * @var string
     */
    private $installerName = self::DEFAULT_INSTALLER_NAME;

    /**
     * @var Uuid[]
     */
    private $disabledBindingUuids = array();

    /**
     * Creates a new install info.
     *
     * @param string $moduleName  The module name. Must be a non-empty string.
     * @param string $installPath The path where the module is installed.
     *                            If a relative path is given, the path is
     *                            assumed to be relative to the install path
     *                            of the root module.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($moduleName, $installPath)
    {
        Assert::moduleName($moduleName);
        Assert::systemPath($installPath);

        $this->moduleName = $moduleName;
        $this->installPath = $installPath;
    }

    /**
     * Returns the path where the module is installed.
     *
     * @return string The path where the module is installed. The path is
     *                either absolute or relative to the install path of the
     *                root module.
     */
    public function getInstallPath()
    {
        return $this->installPath;
    }

    /**
     * Returns the module name.
     *
     * @return null|string Returns the module name or `null` if the name is
     *                     read from the module's puli.json file.
     */
    public function getModuleName()
    {
        return $this->moduleName;
    }

    /**
     * Returns the name of the installer of the module.
     *
     * @return string The module's installer name.
     */
    public function getInstallerName()
    {
        return $this->installerName;
    }

    /**
     * Sets the name of the installer of the module.
     *
     * @param string $installerName The module's installer name.
     */
    public function setInstallerName($installerName)
    {
        $this->installerName = $installerName;
    }

    /**
     * Returns the disabled resource bindings of the module.
     *
     * @return Uuid[] The UUIDs of the disabled resource bindings.
     */
    public function getDisabledBindingUuids()
    {
        return array_values($this->disabledBindingUuids);
    }

    /**
     * Adds a disabled resource binding for the module.
     *
     * @param Uuid $uuid The UUID of the disabled resource binding.
     */
    public function addDisabledBindingUuid(Uuid $uuid)
    {
        $this->disabledBindingUuids[$uuid->toString()] = $uuid;
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
     * Sets the environment that the module is installed in.
     *
     * @param string $env One of the {@link Environment} constants.
     */
    public function setEnvironment($env)
    {
        Assert::oneOf($env, Environment::all(), 'The environment must be one of: %2$s. Got: %s');

        $this->env = $env;
    }

    /**
     * Returns the environment that the module is installed in.
     *
     * @return string Returns one of the {@link Environment} constants.
     */
    public function getEnvironment()
    {
        return $this->env;
    }
}
