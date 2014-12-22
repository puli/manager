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
use Puli\RepositoryManager\Binding\BindingTypeDescriptor;
use Puli\RepositoryManager\Binding\BindingDescriptor;

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
     * @var BindingDescriptor[]
     */
    private $bindingDescriptors = array();

    /**
     * @var BindingTypeDescriptor[]
     */
    private $typeDescriptors = array();

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
     * Returns the binding descriptors.
     *
     * @return BindingDescriptor[] The binding descriptors.
     */
    public function getBindingDescriptors()
    {
        return array_values($this->bindingDescriptors);
    }

    /**
     * Adds a binding descriptor.
     *
     * @param BindingDescriptor $descriptor The binding descriptor to add.
     */
    public function addBindingDescriptor(BindingDescriptor $descriptor)
    {
        $this->bindingDescriptors[] = $descriptor;
    }

    /**
     * Removes a binding descriptor.
     *
     * @param BindingDescriptor $descriptor The binding descriptor to remove.
     */
    public function removeBindingDescriptor(BindingDescriptor $descriptor)
    {
        if (false !== ($key = array_search($descriptor, $this->bindingDescriptors))) {
            unset($this->bindingDescriptors[$key]);
        }
    }

    /**
     * Removes all binding descriptors.
     */
    public function clearBindingDescriptors()
    {
        $this->bindingDescriptors = array();
    }

    /**
     * Returns whether the binding descriptor exists in this file.
     *
     * @param BindingDescriptor $descriptor The binding descriptor.
     *
     * @return bool Whether the file contains the binding descriptor.
     */
    public function hasBindingDescriptor(BindingDescriptor $descriptor)
    {
        return in_array($descriptor, $this->bindingDescriptors);
    }

    /**
     * Returns the type descriptors.
     *
     * @return BindingTypeDescriptor[] The type descriptors.
     */
    public function getTypeDescriptors()
    {
        // Names as keys are for internal use only
        return array_values($this->typeDescriptors);
    }

    /**
     * Adds a type descriptor.
     *
     * @param BindingTypeDescriptor $descriptor The type descriptor.
     */
    public function addTypeDescriptor(BindingTypeDescriptor $descriptor)
    {
        $this->typeDescriptors[$descriptor->getName()] = $descriptor;
    }

    /**
     * Removes a type descriptor.
     *
     * @param string $typeName The type name.
     */
    public function removeTypeDescriptor($typeName)
    {
        unset($this->typeDescriptors[$typeName]);
    }

    /**
     * Removes all type descriptors.
     */
    public function clearTypeDescriptors()
    {
        $this->typeDescriptors = array();
    }

    public function getTypeDescriptor($typeName)
    {
        return $this->typeDescriptors[$typeName];
    }

    /**
     * Returns whether a type is defined in this file.
     *
     * @param string $typeName The type name.
     *
     * @return bool Whether the type is defined in the file.
     */
    public function hasTypeDescriptor($typeName)
    {
        return isset($this->typeDescriptors[$typeName]);
    }
}
