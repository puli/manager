<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Package\Config;

/**
 * The configuration of a Puli package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfig
{
    /**
     * @var string
     */
    private $packageName;

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
     */
    public function __construct($packageName = null)
    {
        $this->packageName = $packageName;
    }

    /**
     * Returns the package name.
     *
     * @return string The package name.
     */
    public function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * Sets the package name.
     *
     * @param string $packageName The package name.
     */
    public function setPackageName($packageName)
    {
        $this->packageName = $packageName;
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
