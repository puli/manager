<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use InvalidArgumentException;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\NoSuchBindingException;
use Puli\Manager\Api\Repository\NoSuchMappingException;
use Puli\Manager\Api\Repository\ResourceMapping;
use Puli\Manager\Assert\Assert;
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
     * @var bool[]
     */
    private $overriddenPackages = array();

    /**
     * @var array
     */
    private $extra = array();

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
     * Sets the names of the packages this package overrides.
     *
     * @param string[] $packageNames The names of the overridden packages.
     */
    public function setOverriddenPackages(array $packageNames)
    {
        $this->overriddenPackages = array();

        foreach ($packageNames as $packageName) {
            $this->overriddenPackages[$packageName] = true;
        }
    }

    /**
     * Adds an overridden package.
     *
     * @param string $packageName The name of the overridden package.
     */
    public function addOverriddenPackage($packageName)
    {
        $this->overriddenPackages[$packageName] = true;
    }

    /**
     * Adds an overridden package.
     *
     * @param string $packageName The name of the overridden package.
     */
    public function removeOverriddenPackage($packageName)
    {
        unset($this->overriddenPackages[$packageName]);
    }

    /**
     * Removes all overridden packages.
     */
    public function clearOverriddenPackages()
    {
        $this->overriddenPackages = array();
    }

    /**
     * Returns the names of the packages this package overrides.
     *
     * @return string[] The names of the overridden packages.
     */
    public function getOverriddenPackages()
    {
        return array_keys($this->overriddenPackages);
    }

    /**
     * Returns whether the package overrides a given package.
     *
     * @param string $packageName The name of the overridden package.
     *
     * @return bool Returns `true` if the package is overridden in the package
     *              file.
     */
    public function hasOverriddenPackage($packageName)
    {
        return isset($this->overriddenPackages[$packageName]);
    }

    /**
     * Returns whether the package overrides any other package.
     *
     * @return bool Returns `true` if the package overrides other packgaes and
     *              `false` otherwise.
     */
    public function hasOverriddenPackages()
    {
        return count($this->overriddenPackages) > 0;
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
     * Returns whether the file contains any resource mappings.
     *
     * @return bool Returns `true` if the file contains resource mappings and
     *              `false` otherwise.
     */
    public function hasResourceMappings()
    {
        return count($this->resourceMappings) > 0;
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
     * Removes all resource mappings.
     */
    public function clearResourceMappings()
    {
        $this->resourceMappings = array();
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
     * Returns whether the file contains any binding descriptors.
     *
     * @return bool Returns `true` if the file contains binding descriptors and
     *              `false` otherwise.
     */
    public function hasBindingDescriptors()
    {
        return count($this->bindingDescriptors) > 0;
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

    /**
     * Returns the type descriptor with the given name.
     *
     * @param string $typeName The type name.
     *
     * @return BindingTypeDescriptor The type descriptor.
     */
    public function getTypeDescriptor($typeName)
    {
        return $this->typeDescriptors[$typeName];
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

    /**
     * Returns whether the file contains any type descriptors.
     *
     * @return bool Returns `true` if the file contains type descriptors and
     *              `false` otherwise.
     */
    public function hasTypeDescriptors()
    {
        return count($this->typeDescriptors) > 0;
    }

    /**
     * Sets an extra key in the package file.
     *
     * Extra keys can be freely set by the user. They are stored in a separate
     * area of the package file and not validated in any way.
     *
     * @param string $key   The name of the key.
     * @param mixed  $value The value to store.
     */
    public function setExtraKey($key, $value)
    {
        $this->extra[$key] = $value;
    }

    /**
     * Sets multiple extra keys at once.
     *
     * Existing extra keys are overridden.
     *
     * @param array $values The values indexed by their key names.
     *
     * @see setExtraKey()
     */
    public function setExtraKeys(array $values)
    {
        $this->extra = array();

        foreach ($values as $key => $value) {
            $this->setExtraKey($key, $value);
        }
    }

    /**
     * Sets multiple extra keys at once.
     *
     * Existing extra keys are preserved.
     *
     * @param array $values The values indexed by their key names.
     *
     * @see setExtraKey()
     */
    public function addExtraKeys(array $values)
    {
        foreach ($values as $key => $value) {
            $this->setExtraKey($key, $value);
        }
    }

    /**
     * Removes an extra key.
     *
     * @param string $key The name of the key.
     *
     * @see setExtraKey()
     */
    public function removeExtraKey($key)
    {
        unset($this->extra[$key]);
    }

    /**
     * Removes all extra keys.
     *
     * @see setExtraKey()
     */
    public function clearExtraKeys()
    {
        $this->extra = array();
    }

    /**
     * Returns the value of an extra key.
     *
     * @param string $key     The name of the key.
     * @param mixed  $default The value to return if the key was not set.
     *
     * @return mixed The value stored for the key.
     *
     * @see setExtraKey()
     */
    public function getExtraKey($key, $default = null)
    {
        return array_key_exists($key, $this->extra) ? $this->extra[$key] : $default;
    }

    /**
     * Returns all stored extra keys.
     *
     * @return array The stored values indexed by their key names.
     *
     * @see setExtraKey()
     */
    public function getExtraKeys()
    {
        return $this->extra;
    }

    /**
     * Returns whether the given extra key exists.
     *
     * @param string $key The name of the key.
     *
     * @return bool Returns `true` if the given extra key exists and `false`
     *              otherwise.
     *
     * @see setExtraKey()
     */
    public function hasExtraKey($key)
    {
        return array_key_exists($key, $this->extra);
    }

    /**
     * Returns whether the file contains any extra keys.
     *
     * @return bool Returns `true` if the file contains extra keys and `false`
     *              otherwise.
     *
     * @see setExtraKey()
     */
    public function hasExtraKeys()
    {
        return count($this->extra) > 0;
    }
}
