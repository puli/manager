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

/**
 * Describes a package in the install file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageMetadata
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
    private $name;

    /**
     * @var string
     */
    private $installer = self::DEFAULT_INSTALLER;

    /**
     * Creates new package metadata.
     *
     * @param string $installPath The path where the package is installed.
     *                            If a relative path is given, the path is
     *                            assumed to be relative to the install path
     *                            of the root package.
     *
     * @throws \InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($installPath)
    {
        if (!is_string($installPath)) {
            throw new \InvalidArgumentException(sprintf(
                'The package install path must be a string. Got: %s',
                is_object($installPath) ? get_class($installPath) : gettype($installPath)
            ));
        }

        if ('' === $installPath) {
            throw new \InvalidArgumentException('The package install path must not be empty.');
        }

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
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the package name.
     *
     * @param null|string $name The package name or `null` if the name should
     *                          be read from the package's puli.json file.
     *
     * @throws \InvalidArgumentException If the package name is not a string.
     */
    public function setName($name)
    {
        if (!is_string($name) && null !== $name) {
            throw new \InvalidArgumentException(sprintf(
                'The package name must be a string or null. Got: %s',
                is_object($name) ? get_class($name) : gettype($name)
            ));
        }

        if ('' === $name) {
            throw new \InvalidArgumentException('The package name must not be empty.');
        }

        $this->name = $name;
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
}
