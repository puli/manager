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
    private $resourceDefinitions = array();

    /**
     * @var string[]
     */
    private $tagDefinitions = array();

    /**
     * @var string[]
     */
    private $overriddenPackages = array();

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
     * @param string[] $overriddenPackages The names of the overridden packages.
     */
    public function setOverriddenPackages(array $overriddenPackages)
    {
        $this->overriddenPackages = $overriddenPackages;
    }

    /**
     * Returns the resource definitions.
     *
     * @return ResourceDefinition[] The resource definition.
     */
    public function getResourceDefinitions()
    {
        return $this->resourceDefinitions;
    }

    /**
     * Adds a resource definition.
     *
     * @param ResourceDefinition $resourceDefinition The resource definition.
     */
    public function addResourceDefinition(ResourceDefinition $resourceDefinition)
    {
        $this->resourceDefinitions[] = $resourceDefinition;
    }

    /**
     * Returns the tag definitions.
     *
     * @return TagDefinition[] The tag definitions.
     */
    public function getTagDefinitions()
    {
        return $this->tagDefinitions;
    }

    /**
     * Adds a tag definition.
     *
     * @param TagDefinition $tagDefinition The tag definition.
     */
    public function addTagDefinition(TagDefinition $tagDefinition)
    {
        $this->tagDefinitions[] = $tagDefinition;
    }
}
