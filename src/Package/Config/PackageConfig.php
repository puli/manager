<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Config;

/**
 * The configuration of a Puli package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfig
{
    /**
     * @var string|null
     */
    private $packageName;

    /**
     * @var string|null
     */
    private $path;

    /**
     * @var string[]
     */
    private $resourceDescriptors = array();

    /**
     * @var string[]
     */
    private $tagDescriptors = array();

    /**
     * @var string[]
     */
    private $overriddenPackages = array();

    /**
     * Creates a new package configuration.
     *
     * @param string|null $packageName The package name. Optional.
     * @param string|null $path        The path where the configuration is
     *                                 stored or `null` if this configuration is
     *                                 not stored on the file system.
     *
     * @throws \InvalidArgumentException If the path is not a string or empty.
     */
    public function __construct($packageName = null, $path = null)
    {
        if (!is_string($path) && null !== $path) {
            throw new \InvalidArgumentException(sprintf(
                'The path to the package configuration should be a string '.
                'or null. Got: %s',
                is_object($path) ? get_class($path) : gettype($path)
            ));
        }

        if ('' === $path) {
            throw new \InvalidArgumentException('The path to the package configuration should not be empty.');
        }

        $this->packageName = $packageName;
        $this->path = $path;
    }

    /**
     * Returns the package name.
     *
     * @return string|null The package name or `null` if none is set.
     */
    public function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * Sets the package name.
     *
     * @param string|null $packageName The package name or `null` to unset.
     */
    public function setPackageName($packageName)
    {
        $this->packageName = $packageName;
    }

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
     * Returns the names of the packages this package overrides.
     *
     * @return string[] The names of the overridden packages.
     */
    public function getOverriddenPackages()
    {
        return $this->overriddenPackages;
    }

    /**
     * Sets the names of the packages this package overrides.
     *
     * @param string|string[] $overriddenPackages The names of the overridden packages.
     */
    public function setOverriddenPackages($overriddenPackages)
    {
        $this->overriddenPackages = (array) $overriddenPackages;
    }

    /**
     * Returns the resource descriptors.
     *
     * @return ResourceDescriptor[] The resource descriptor.
     */
    public function getResourceDescriptors()
    {
        return $this->resourceDescriptors;
    }

    /**
     * Adds a resource descriptor.
     *
     * @param ResourceDescriptor $resourceDescriptor The resource descriptor.
     */
    public function addResourceDescriptor(ResourceDescriptor $resourceDescriptor)
    {
        $this->resourceDescriptors[] = $resourceDescriptor;
    }

    /**
     * Returns the tag descriptors.
     *
     * @return TagDescriptor[] The tag descriptors.
     */
    public function getTagDescriptors()
    {
        return $this->tagDescriptors;
    }

    /**
     * Adds a tag descriptor.
     *
     * @param TagDescriptor $tagDescriptor The tag descriptor.
     */
    public function addTagDescriptor(TagDescriptor $tagDescriptor)
    {
        $this->tagDescriptors[] = $tagDescriptor;
    }
}
