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

use Assert\Assertion;
use InvalidArgumentException;
use Puli\RepositoryManager\Binding\BindingDescriptor;

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
    const DEFAULT_INSTALLER = 'User';

    /**
     * @var string
     */
    private $installPath;

    /**
     * @var string|null
     */
    private $packageName;

    /**
     * @var string
     */
    private $installer = self::DEFAULT_INSTALLER;

    /**
     * @var BindingDescriptor[]
     */
    private $enabledBindings = array();

    /**
     * @var BindingDescriptor[]
     */
    private $disabledBindings = array();

    /**
     * Creates a new install info.
     *
     * @param string $packageName The package name. Must be a non-empty string.
     * @param string $installPath The path where the package is installed.
     *                            If a relative path is given, the path is
     *                            assumed to be relative to the install path
     *                            of the root package.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($packageName, $installPath)
    {
        Assertion::string($packageName, 'The package name must be a string. Got: %2$s');
        Assertion::notEmpty($packageName, 'The package name must not be empty.');
        Assertion::string($installPath, 'The package install path must be a string. Got: %2$s');
        Assertion::notEmpty($installPath, 'The package install path must not be empty.');

        $this->packageName = $packageName;
        $this->installPath = $installPath;
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
     * Returns the installer of the package.
     *
     * @return string The package's installer.
     */
    public function getInstaller()
    {
        return $this->installer;
    }

    /**
     * Sets the installer of the package.
     *
     * @param string $installer The package's installer.
     */
    public function setInstaller($installer)
    {
        $this->installer = $installer;
    }

    /**
     * Returns the enabled resource bindings of the package.
     *
     * @return BindingDescriptor[] The enabled resource bindings.
     */
    public function getEnabledBindings()
    {
        return $this->enabledBindings;
    }

    /**
     * Adds an enabled resource binding for the package.
     *
     * @param BindingDescriptor $binding The enabled resource binding.
     */
    public function addEnabledBinding(BindingDescriptor $binding)
    {
        if (in_array($binding, $this->enabledBindings)) {
            return;
        }

        $this->enabledBindings[] = $binding;

        $this->removeDisabledBinding($binding);
    }

    /**
     * Returns whether the resource binding is enabled.
     *
     * @param BindingDescriptor $binding The resource binding.
     *
     * @return bool Whether the resource binding is enabled.
     */
    public function hasEnabledBinding(BindingDescriptor $binding)
    {
        return in_array($binding, $this->enabledBindings);
    }

    /**
     * Removes an enabled resource binding.
     *
     * If the resource binding is not enabled, this method does nothing.
     *
     * @param BindingDescriptor $binding The resource binding to remove.
     */
    public function removeEnabledBinding(BindingDescriptor $binding)
    {
        if (false !== ($key = array_search($binding, $this->enabledBindings))) {
            unset($this->enabledBindings[$key]);
        }
    }

    /**
     * Returns the disabled resource bindings of the package.
     *
     * @return BindingDescriptor[] The disabled resource bindings.
     */
    public function getDisabledBindings()
    {
        return $this->disabledBindings;
    }

    /**
     * Adds a disabled resource binding for the package.
     *
     * @param BindingDescriptor $binding The disabled resource binding.
     */
    public function addDisabledBinding(BindingDescriptor $binding)
    {
        if (in_array($binding, $this->disabledBindings)) {
            return;
        }

        $this->disabledBindings[] = $binding;

        $this->removeEnabledBinding($binding);
    }

    /**
     * Returns whether the resource binding is disabled.
     *
     * @param BindingDescriptor $binding The resource binding.
     *
     * @return bool Whether the resource binding is disabled.
     */
    public function hasDisabledBinding(BindingDescriptor $binding)
    {
        return in_array($binding, $this->disabledBindings);
    }

    /**
     * Removes a disabled resource binding.
     *
     * If the resource binding is not disabled, this method does nothing.
     *
     * @param BindingDescriptor $binding The resource binding to remove.
     */
    public function removeDisabledBinding(BindingDescriptor $binding)
    {
        if (false !== ($key = array_search($binding, $this->disabledBindings))) {
            unset($this->disabledBindings[$key]);
        }
    }
}
