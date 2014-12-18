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

use Assert\Assertion;
use InvalidArgumentException;
use Puli\RepositoryManager\Package\ResourceMapping;
use Puli\RepositoryManager\Tag\TagDefinition;
use Puli\RepositoryManager\Tag\TagMapping;

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
     * @var TagDefinition[]
     */
    private $tagDefinitions = array();

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
     * @throws InvalidArgumentException If the name/path is not a string or empty.
     */
    public function __construct($packageName = null, $path = null)
    {
        Assertion::nullOrString($path, 'The path to the package file should be a string or null. Got: %2$s');
        Assertion::nullOrNotEmpty($path, 'The path to the package file should not be empty.');

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
     * @throws InvalidArgumentException If the name is not a string or empty.
     */
    public function setPackageName($packageName)
    {
        Assertion::nullOrString($packageName, 'The package name should be a string or null. Got: %2$s');
        Assertion::nullOrNotEmpty($packageName, 'The package name should not be empty.');

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
        return array_values($this->tagMappings);
    }

    /**
     * Adds a tag mapping.
     *
     * @param TagMapping $tagMapping The tag mapping to add.
     */
    public function addTagMapping(TagMapping $tagMapping)
    {
        $this->tagMappings[] = $tagMapping;
    }

    /**
     * Removes a tag mapping.
     *
     * @param TagMapping $tagMapping The tag mapping to remove.
     */
    public function removeTagMapping(TagMapping $tagMapping)
    {
        if (false !== ($key = array_search($tagMapping, $this->tagMappings))) {
            unset($this->tagMappings[$key]);
        }
    }

    /**
     * Removes all tag mappings.
     */
    public function clearTagMappings()
    {
        $this->tagMappings = array();
    }

    /**
     * Returns whether the tag mapping exists in this file.
     *
     * @param TagMapping $tagMapping The tag mapping.
     *
     * @return bool Whether the file contains the tag mapping.
     */
    public function hasTagMapping(TagMapping $tagMapping)
    {
        return in_array($tagMapping, $this->tagMappings);
    }

    /**
     * Returns the tag definitions.
     *
     * @return TagDefinition[] The tag definitions.
     */
    public function getTagDefinitions()
    {
        // Tags as keys are for internal use only
        return array_values($this->tagDefinitions);
    }

    /**
     * Adds a tag definition.
     *
     * @param TagDefinition $tagDefinition The tag definition.
     */
    public function addTagDefinition(TagDefinition $tagDefinition)
    {
        $this->tagDefinitions[$tagDefinition->getTag()] = $tagDefinition;
    }

    /**
     * Removes a tag definition.
     *
     * @param string $tag The tag.
     */
    public function removeTagDefinition($tag)
    {
        unset($this->tagDefinitions[$tag]);
    }

    /**
     * Removes all tag definitions.
     */
    public function clearTagDefinitions()
    {
        $this->tagDefinitions = array();
    }

    /**
     * Returns whether a tag is defined in this file.
     *
     * @param string $tag The tag.
     *
     * @return bool Whether the tag is defined in the file.
     */
    public function hasTagDefinition($tag)
    {
        return isset($this->tagDefinitions[$tag]);
    }
}
