<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Repository\Config;

/**
 * The configuration of the package repository.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRepositoryConfig
{
    /**
     * @var string|null
     */
    private $path;

    /**
     * @var PackageDescriptor[]
     */
    private $packageDescriptors = array();

    /**
     * Returns the file system path of the configuration file.
     *
     * @return string|null The path or `null` if this configuration is not
     *                     stored on the file system.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets the file system path where the configuration file is stored.
     *
     * @param string|null $path The path or `null` if this configuration is not
     *                          stored on the file system.
     */
    public function setPath($path)
    {
        if (!is_string($path) && null !== $path) {
            throw new \InvalidArgumentException(sprintf(
                'The path to the repository configuration should be a string '.
                'or null. Got: %s',
                is_object($path) ? get_class($path) : gettype($path)
            ));
        }

        if ('' === $path) {
            throw new \InvalidArgumentException('The path to the repository configuration should not be empty.');
        }

        $this->path = $path;
    }

    /**
     * Returns the package descriptors.
     *
     * @return PackageDescriptor[] The package descriptors.
     */
    public function getPackageDescriptors()
    {
        return $this->packageDescriptors;
    }

    /**
     * Sets the package descriptors.
     *
     * @param PackageDescriptor[] $descriptors The package descriptors.
     */
    public function setPackageDescriptors(array $descriptors)
    {
        $this->packageDescriptors = $descriptors;
    }

    /**
     * Adds a package descriptor.
     *
     * @param PackageDescriptor $descriptor The package descriptor.
     */
    public function addPackageDescriptor(PackageDescriptor $descriptor)
    {
        $this->packageDescriptors[] = $descriptor;
    }
}
