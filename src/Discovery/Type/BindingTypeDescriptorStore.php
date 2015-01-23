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
use Puli\RepositoryManager\Api\Package\Package;
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
class BindingTypeDescriptorStore
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
     * @param Package               $package        The package defining the
     *                                              type descriptor.
     */
    public function add(BindingTypeDescriptor $typeDescriptor, Package $package)
    {
        $this->map->set($typeDescriptor->getName(), $package->getName(), $typeDescriptor);
    }

    /**
     * Removes a type descriptor.
     *
     * This method ignores non-existing type descriptors.
     *
     * @param string  $typeName The name of the type.
     * @param Package $package  The package containing the type.
     */
    public function remove($typeName, Package $package)
    {
        $this->map->remove($typeName, $package->getName());
    }

    /**
     * Returns a type descriptor.
     *
     * If no package is passed, the first descriptor set for the type name is
     * returned.
     *
     * @param string  $typeName The name of the type.
     * @param Package $package  The package containing the type.
     *
     * @return BindingTypeDescriptor The type descriptor.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name/package.
     */
    public function get($typeName, Package $package = null)
    {
        if (null !== $package) {
            return $this->map->get($typeName, $package->getName());
        }

        return $this->map->getFirst($typeName);
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
    public function getAll($typeName)
    {
        return $this->map->listByPrimaryKey($typeName);
    }

    /**
     * Returns whether a type descriptor was set for the given name/package.
     *
     * @param string  $typeName The name of the type.
     * @param Package $package  The package containing the type.
     *
     * @return bool Returns `true` if a type descriptor was set for the given
     *              name/package.
     */
    public function exists($typeName, Package $package)
    {
        return $this->map->contains($typeName, $package->getName());
    }

    /**
     * Returns whether a type descriptor was set for the given name in any
     * package.
     *
     * @param string $typeName The name of the type.
     *
     * @return bool Returns `true` if a type descriptor was set for the given
     *              name.
     */
    public function existsAny($typeName)
    {
        return $this->map->contains($typeName);
    }

    /**
     * Returns whether an enabled type descriptor was set for the given name
     * in any package.
     *
     * @param string $typeName The name of the type.
     *
     * @return bool Returns `true` if an enabled type descriptor was set for
     *              the given name.
     */
    public function existsEnabled($typeName)
    {
        try {
            foreach ($this->getAll($typeName) as $typeDescriptor) {
                if ($typeDescriptor->isEnabled()) {
                    return true;
                }
            }
        } catch (OutOfBoundsException $e) {
        }

        return false;
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
    public function getDefiningPackageNames($typeName)
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
