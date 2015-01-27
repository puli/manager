<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Type;

use OutOfBoundsException;
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Util\TwoDimensionalHashMap;

/**
 * A store for binding type descriptors.
 *
 * Each descriptor has a composite key:
 *
 *  * The name of the type.
 *  * The package that defines the type.
 *
 * The store implements transparent merging of types defined within different
 * packages, but with the same type name. If a type is requested for a name
 * without giving a package name, the first type set for that name is
 * returned by {@link get()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingTypeDescriptorCollection
{
    /**
     * @var TwoDimensionalHashMap
     */
    private $map;

    /**
     * Creates the store.
     */
    public function __construct()
    {
        $this->map = new TwoDimensionalHashMap();
    }

    /**
     * Adds a type descriptor.
     *
     * @param BindingTypeDescriptor $typeDescriptor The type descriptor.
     */
    public function add(BindingTypeDescriptor $typeDescriptor)
    {
        $this->map->set($typeDescriptor->getName(), $typeDescriptor->getContainingPackage()->getName(), $typeDescriptor);
    }

    /**
     * Removes a type descriptor.
     *
     * This method ignores non-existing type descriptors.
     *
     * @param string $typeName    The name of the type.
     * @param string $packageName The name of the package containing the type.
     */
    public function remove($typeName, $packageName)
    {
        $this->map->remove($typeName, $packageName);
    }

    /**
     * Returns a type descriptor.
     *
     * If no package is passed, the first descriptor set for the type name is
     * returned.
     *
     * @param string $typeName    The name of the type.
     * @param string $packageName The name of the package containing the type.
     *
     * @return BindingTypeDescriptor The type descriptor.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name/package.
     */
    public function get($typeName, $packageName = null)
    {
        if (null !== $packageName) {
            return $this->map->get($typeName, $packageName);
        }

        return $this->map->getFirst($typeName);
    }

    /**
     * Returns the enabled type descriptor for a given type name.
     *
     * @param string $typeName The name of the type.
     *
     * @return BindingTypeDescriptor|null The enabled type descriptor or `null`
     *                                    if no enabled descriptor was found.
     */
    public function getEnabled($typeName)
    {
        if (!$this->contains($typeName)) {
            return null;
        }

        foreach ($this->listByTypeName($typeName) as $typeDescriptor) {
            if ($typeDescriptor->isEnabled()) {
                return $typeDescriptor;
            }
        }

        return null;
    }

    /**
     * Returns all type descriptors set for the given name.
     *
     * @param string $typeName The name of the type.
     *
     * @return BindingTypeDescriptor[] The type descriptors.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name.
     */
    public function listByTypeName($typeName)
    {
        return $this->map->listByPrimaryKey($typeName);
    }

    /**
     * Returns whether a type descriptor was set for the given name/package.
     *
     * @param string $typeName    The name of the type.
     * @param string $packageName The name of the package containing the type.
     *
     * @return bool Returns `true` if a type descriptor was set for the given
     *              name/package.
     */
    public function contains($typeName, $packageName = null)
    {
        return $this->map->contains($typeName, $packageName);
    }

    /**
     * Returns the names of the packages defining types with the given name.
     *
     * @param string $typeName The name of the type.
     *
     * @return string[] The package names.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name.
     */
    public function getPackageNames($typeName = null)
    {
        return $this->map->getSecondaryKeys($typeName);
    }

    /**
     * Returns the names of all type descriptors.
     *
     * @return string[] The names of the stored types.
     */
    public function getTypeNames()
    {
        return $this->map->getPrimaryKeys();
    }
}
