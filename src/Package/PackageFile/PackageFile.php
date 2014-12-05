<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile;

use Puli\RepositoryManager\Package\ResourceMapping;
use Puli\RepositoryManager\Package\TagMapping;

/**
 * Stores the configuration of a package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFile
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
     * @var ResourceMapping[]
     */
    private $resourceMappings = array();

    /**
     * @var TagMapping[]
     */
    private $tagMappings = array();

    /**
     * @var string[]
     */
    private $overriddenPackages = array();

    /**
     * Creates a new package file.
     *
     * @param string|null $packageName The package name. Optional.
     * @param string|null $path        The path where the file is stored or
     *                                 `null` if this configuration is not
     *                                 stored on the file system.
     *
     * @throws \InvalidArgumentException If the name/path is not a string or empty.
     */
    public function __construct($packageName = null, $path = null)
    {
        if (!is_string($path) && null !== $path) {
            throw new \InvalidArgumentException(sprintf(
                'The path to the package file should be a string or null. Got: %s',
                is_object($path) ? get_class($path) : gettype($path)
            ));
        }

        if ('' === $path) {
            throw new \InvalidArgumentException('The path to the package file should not be empty.');
        }

        $this->path = $path;
        $this->setPackageName($packageName);
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
     *
     * @throws \InvalidArgumentException If the name is not a string or empty.
     */
    public function setPackageName($packageName)
    {
        if (!is_string($packageName) && null !== $packageName) {
            throw new \InvalidArgumentException(sprintf(
                'The package name should be a string or null. Got: %s',
                is_object($packageName) ? get_class($packageName) : gettype($packageName)
            ));
        }

        if ('' === $packageName) {
            throw new \InvalidArgumentException('The package name should not be empty.');
        }

        $this->packageName = $packageName;
    }

    /**
     * Returns the path to the package file.
     *
     * @return string|null The path or `null` if this file is not stored on the
     *                     file system.
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
     * Returns the resource mappings.
     *
     * @return ResourceMapping[] The resource mappings.
     */
    public function getResourceMappings()
    {
        return $this->resourceMappings;
    }

    /**
     * Adds a resource mapping.
     *
     * @param ResourceMapping $resourceMapping The resource mapping.
     */
    public function addResourceMapping(ResourceMapping $resourceMapping)
    {
        $this->resourceMappings[] = $resourceMapping;
    }

    /**
     * Returns the tag mappings.
     *
     * @return TagMapping[] The tag mappings.
     */
    public function getTagMappings()
    {
        return $this->tagMappings;
    }

    /**
     * Adds a tag mapping.
     *
     * @param TagMapping $tagMapping The tag mapping.
     */
    public function addTagMapping(TagMapping $tagMapping)
    {
        $this->tagMappings[] = $tagMapping;
    }
}
