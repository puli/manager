<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Module;

use InvalidArgumentException;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\NoSuchBindingException;
use Puli\Manager\Api\Repository\NoSuchPathMappingException;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Assert\Assert;
use Rhumsaa\Uuid\Uuid;

/**
 * Stores the configuration of a module.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleFile
{
    /**
     * The current puli.json version.
     */
    const DEFAULT_VERSION = '1.0';

    /**
     * @var string
     */
    private $version = self::DEFAULT_VERSION;

    /**
     * @var string|null
     */
    private $moduleName;

    /**
     * @var string|null
     */
    private $path;

    /**
     * @var PathMapping[]
     */
    private $pathMappings = array();

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
    private $overriddenModules = array();

    /**
     * @var array
     */
    private $extra = array();

    /**
     * Creates a new module file.
     *
     * @param string|null $moduleName The module name. Optional.
     * @param string|null $path       The path where the file is stored or
     *                                `null` if this configuration is not
     *                                stored on the file system.
     *
     * @throws InvalidArgumentException If the name/path is not a string or empty.
     */
    public function __construct($moduleName = null, $path = null)
    {
        Assert::nullOrModuleName($moduleName);
        Assert::nullOrAbsoluteSystemPath($path);

        $this->path = $path;
        $this->moduleName = $moduleName;
    }

    /**
     * Returns the version of the module file.
     *
     * @return string The module file version.
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Sets the version of the module file.
     *
     * @param string $version The module file version.
     */
    public function setVersion($version)
    {
        Assert::string($version, 'The module file version must be a string. Got: %s');
        Assert::regex($version, '~^\d\.\d$~', 'The module file version must have the format "<digit>.<digit>". Got: %s</digit>');

        $this->version = $version;
    }

    /**
     * Returns the module name.
     *
     * @return string|null The module name or `null` if none is set.
     */
    public function getModuleName()
    {
        return $this->moduleName;
    }

    /**
     * Sets the module name.
     *
     * @param string|null $moduleName The module name or `null` to unset.
     *
     * @throws InvalidArgumentException If the name is not a string or empty.
     */
    public function setModuleName($moduleName)
    {
        Assert::nullOrModuleName($moduleName);

        $this->moduleName = $moduleName;
    }

    /**
     * Returns the path to the module file.
     *
     * @return string|null The path or `null` if this file is not stored on the
     *                     file system.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets the names of the modules this module overrides.
     *
     * @param string[] $moduleNames The names of the overridden modules.
     */
    public function setOverriddenModules(array $moduleNames)
    {
        $this->overriddenModules = array();

        foreach ($moduleNames as $moduleName) {
            $this->overriddenModules[$moduleName] = true;
        }
    }

    /**
     * Adds an overridden module.
     *
     * @param string $moduleName The name of the overridden module.
     */
    public function addOverriddenModule($moduleName)
    {
        $this->overriddenModules[$moduleName] = true;
    }

    /**
     * Adds an overridden module.
     *
     * @param string $moduleName The name of the overridden module.
     */
    public function removeOverriddenModule($moduleName)
    {
        unset($this->overriddenModules[$moduleName]);
    }

    /**
     * Removes all overridden modules.
     */
    public function clearOverriddenModules()
    {
        $this->overriddenModules = array();
    }

    /**
     * Returns the names of the modules this module overrides.
     *
     * @return string[] The names of the overridden modules.
     */
    public function getOverriddenModules()
    {
        return array_keys($this->overriddenModules);
    }

    /**
     * Returns whether the module overrides a given module.
     *
     * @param string $moduleName The name of the overridden module.
     *
     * @return bool Returns `true` if the module is overridden in the module
     *              file.
     */
    public function hasOverriddenModule($moduleName)
    {
        return isset($this->overriddenModules[$moduleName]);
    }

    /**
     * Returns whether the module overrides any other module.
     *
     * @return bool Returns `true` if the module overrides other packgaes and
     *              `false` otherwise.
     */
    public function hasOverriddenModules()
    {
        return count($this->overriddenModules) > 0;
    }

    /**
     * Returns the path mappings.
     *
     * @return PathMapping[] The path mappings.
     */
    public function getPathMappings()
    {
        return $this->pathMappings;
    }

    /**
     * Returns the path mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return PathMapping The corresponding path mapping.
     *
     * @throws NoSuchPathMappingException If the repository path is not mapped.
     */
    public function getPathMapping($repositoryPath)
    {
        if (!isset($this->pathMappings[$repositoryPath])) {
            throw NoSuchPathMappingException::forRepositoryPath($repositoryPath);
        }

        return $this->pathMappings[$repositoryPath];
    }

    /**
     * Returns whether the file contains a path mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     *
     * @return bool Returns `true` if the file contains a mapping for the path.
     */
    public function hasPathMapping($repositoryPath)
    {
        return isset($this->pathMappings[$repositoryPath]);
    }

    /**
     * Returns whether the file contains any path mappings.
     *
     * @return bool Returns `true` if the file contains path mappings and
     *              `false` otherwise.
     */
    public function hasPathMappings()
    {
        return count($this->pathMappings) > 0;
    }

    /**
     * Adds a path mapping.
     *
     * @param PathMapping $mapping The path mapping.
     */
    public function addPathMapping(PathMapping $mapping)
    {
        $this->pathMappings[$mapping->getRepositoryPath()] = $mapping;

        ksort($this->pathMappings);
    }

    /**
     * Removes the path mapping for a repository path.
     *
     * @param string $repositoryPath The repository path.
     */
    public function removePathMapping($repositoryPath)
    {
        unset($this->pathMappings[$repositoryPath]);
    }

    /**
     * Removes all path mappings.
     */
    public function clearPathMappings()
    {
        $this->pathMappings = array();
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
        $this->typeDescriptors[$descriptor->getTypeName()] = $descriptor;
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
     * Sets an extra key in the module file.
     *
     * Extra keys can be freely set by the user. They are stored in a separate
     * area of the module file and not validated in any way.
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
