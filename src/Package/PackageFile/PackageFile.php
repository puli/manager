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

use InvalidArgumentException;
use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\NoSuchBindingException;
use Puli\RepositoryManager\Repository\NoSuchMappingException;
use Puli\RepositoryManager\Repository\ResourceMapping;
use Rhumsaa\Uuid\Uuid;

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
        Assert::nullOrPackageName($packageName);
        Assert::nullOrAbsoluteSystemPath($path);

        $this->path = $path;
        $this->packageName = $packageName;
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
        Assert::nullOrPackageName($packageName);

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
     * Adds an overridden package.
     *
     * @param string $overriddenPackage The name of the overridden package.
     */
    public function addOverriddenPackage($overriddenPackage)
    {
        if (!in_array($overriddenPackage, $this->overriddenPackages)) {
            $this->overriddenPackages[] = $overriddenPackage;
        }
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
     * Returns the resource mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return ResourceMapping The corresponding resource mapping.
     *
     * @throws NoSuchMappingException If the repository path is not mapped.
     */
    public function getResourceMapping($repositoryPath)
    {
        if (!isset($this->resourceMappings[$repositoryPath])) {
            throw NoSuchMappingException::forRepositoryPath($repositoryPath);
        }

        return $this->resourceMappings[$repositoryPath];
    }

    /**
     * Returns whether the file contains a resource mapping for a repository
     * path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return bool Returns `true` if the file contains a mapping for the path.
     */
    public function hasResourceMapping($repositoryPath)
    {
        return isset($this->resourceMappings[$repositoryPath]);
    }

    /**
     * Adds a resource mapping.
     *
     * @param ResourceMapping $mapping The resource mapping.
     */
    public function addResourceMapping(ResourceMapping $mapping)
    {
        $this->resourceMappings[$mapping->getRepositoryPath()] = $mapping;

        ksort($this->resourceMappings);
    }

    /**
     * Removes the resource mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     */
    public function removeResourceMapping($repositoryPath)
    {
        unset($this->resourceMappings[$repositoryPath]);
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
     * Returns the binding descriptor with the given UUID.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return BindingDescriptor The binding descriptor.
     *
     * @throws NoSuchBindingException If the UUID was not found.
     */
    public function getBindingDescriptor(Uuid $uuid)
    {
        $uuidString = $uuid->toString();

        if (!isset($this->bindingDescriptors[$uuidString])) {
            throw NoSuchBindingException::forUuid($uuid);
        }

        return $this->bindingDescriptors[$uuidString];
    }

    /**
     * Adds a binding descriptor.
     *
     * @param BindingDescriptor $descriptor The binding descriptor to add.
     */
    public function addBindingDescriptor(BindingDescriptor $descriptor)
    {
        $this->bindingDescriptors[$descriptor->getUuid()->toString()] = $descriptor;
    }

    /**
     * Removes a binding descriptor.
     *
     * @param Uuid $uuid The UUID of the binding descriptor to remove.
     */
    public function removeBindingDescriptor(Uuid $uuid)
    {
        unset($this->bindingDescriptors[$uuid->toString()]);
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
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return bool Whether the file contains the binding descriptor.
     */
    public function hasBindingDescriptor(Uuid $uuid)
    {
        return isset($this->bindingDescriptors[$uuid->toString()]);
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
