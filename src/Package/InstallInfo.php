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
use Puli\RepositoryManager\Tag\TagMapping;

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
     * @var TagMapping[]
     */
    private $enabledTagMappings = array();

    /**
     * @var TagMapping[]
     */
    private $disabledTagMappings = array();

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
     * Returns the enabled tag mappings of the package.
     *
     * @return TagMapping[] The enabled tag mappings.
     */
    public function getEnabledTagMappings()
    {
        return $this->enabledTagMappings;
    }

    /**
     * Adds an enabled tag mapping for the package.
     *
     * @param TagMapping $tagMapping The enabled tag mapping.
     */
    public function addEnabledTagMapping(TagMapping $tagMapping)
    {
        if (in_array($tagMapping, $this->enabledTagMappings)) {
            return;
        }

        $this->enabledTagMappings[] = $tagMapping;

        $this->removeDisabledTagMapping($tagMapping);
    }

    /**
     * Returns whether the tag mapping is enabled.
     *
     * @param TagMapping $tagMapping The tag mapping.
     *
     * @return bool Whether the tag mapping is enabled.
     */
    public function hasEnabledTagMapping(TagMapping $tagMapping)
    {
        return in_array($tagMapping, $this->enabledTagMappings);
    }

    /**
     * Removes an enabled tag mapping.
     *
     * If the tag mapping is not enabled, this method does nothing.
     *
     * @param TagMapping $tagMapping The tag mapping to remove.
     */
    public function removeEnabledTagMapping(TagMapping $tagMapping)
    {
        if (false !== ($key = array_search($tagMapping, $this->enabledTagMappings))) {
            unset($this->enabledTagMappings[$key]);
        }
    }

    /**
     * Returns the disabled tag mappings of the package.
     *
     * @return TagMapping[] The disabled tag mappings.
     */
    public function getDisabledTagMappings()
    {
        return $this->disabledTagMappings;
    }

    /**
     * Adds a disabled tag mapping for the package.
     *
     * @param TagMapping $tagMapping The disabled tag mapping.
     */
    public function addDisabledTagMapping(TagMapping $tagMapping)
    {
        if (in_array($tagMapping, $this->disabledTagMappings)) {
            return;
        }

        $this->disabledTagMappings[] = $tagMapping;

        $this->removeEnabledTagMapping($tagMapping);
    }

    /**
     * Returns whether the tag mapping is disabled.
     *
     * @param TagMapping $tagMapping The tag mapping.
     *
     * @return bool Whether the tag mapping is disabled.
     */
    public function hasDisabledTagMapping(TagMapping $tagMapping)
    {
        return in_array($tagMapping, $this->disabledTagMappings);
    }

    /**
     * Removes a disabled tag mapping.
     *
     * If the tag mapping is not disabled, this method does nothing.
     *
     * @param TagMapping $tagMapping The tag mapping to remove.
     */
    public function removeDisabledTagMapping(TagMapping $tagMapping)
    {
        if (false !== ($key = array_search($tagMapping, $this->disabledTagMappings))) {
            unset($this->disabledTagMappings[$key]);
        }
    }
}
