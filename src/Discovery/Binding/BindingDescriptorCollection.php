<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Binding;

use OutOfBoundsException;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Util\TwoDimensionalHashMap;
use Rhumsaa\Uuid\Uuid;

/**
 * A collection of binding descriptors.
 *
 * Each descriptor has a composite key:
 *
 *  * The UUID of the binding descriptor.
 *  * The package that defines the binding.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorCollection
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
     * Adds a binding descriptor.
     *
     * @param BindingDescriptor $bindingDescriptor The binding descriptor.
     */
    public function add(BindingDescriptor $bindingDescriptor)
    {
        $this->map->set($bindingDescriptor->getUuid()->toString(), $bindingDescriptor->getContainingPackage()->getName(), $bindingDescriptor);
    }

    /**
     * Removes a binding descriptor.
     *
     * This method ignores non-existing binding descriptors.
     *
     * @param Uuid   $uuid        The UUID of the binding descriptor.
     * @param string $packageName The name of the package containing the descriptor.
     */
    public function remove(Uuid $uuid, $packageName)
    {
        $this->map->remove($uuid->toString(), $packageName);
    }

    /**
     * Returns a binding descriptor.
     *
     * If no package is passed, the first descriptor set for the UUID is
     * returned.
     *
     * @param Uuid   $uuid        The UUID of the binding descriptor.
     * @param string $packageName The name of the package containing the descriptor.
     *
     * @return BindingDescriptor The binding descriptor.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID/package.
     */
    public function get(Uuid $uuid, $packageName = null)
    {
        if (null !== $packageName) {
            return $this->map->get($uuid->toString(), $packageName);
        }

        return $this->map->getFirst($uuid->toString());
    }

    /**
     * Returns the enabled binding descriptor for a given UUID.
     *
     * If no descriptor is set or if none is enabled, `null` is returned.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return BindingDescriptor|null The binding descriptor or none if no
     *                                (enabled) descriptor could be found.
     */
    public function getEnabled(Uuid $uuid)
    {
        if (!$this->contains($uuid)) {
            return null;
        }

        foreach ($this->listByUuid($uuid) as $bindingDescriptor) {
            if ($bindingDescriptor->isEnabled()) {
                return $bindingDescriptor;
            }
        }

        return null;
    }

    /**
     * Returns all binding descriptors set for the given UUID.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return BindingDescriptor[] The binding descriptors.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID.
     */
    public function listByUuid(Uuid $uuid)
    {
        return $this->map->listByPrimaryKey($uuid->toString());
    }

    /**
     * Returns whether a binding descriptor was set for the given UUID/package.
     *
     * @param Uuid   $uuid        The UUID of the binding descriptor.
     * @param string $packageName The name of the package containing the descriptor.
     *
     * @return bool Returns `true` if a binding descriptor was set for the given
     *              UUID/package.
     */
    public function contains(Uuid $uuid, $packageName = null)
    {
        return $this->map->contains($uuid->toString(), $packageName);
    }

    /**
     * Returns the names of the packages defining bindings with the given UUID.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return string[] The package names.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID.
     */
    public function getPackageNames(Uuid $uuid = null)
    {
        return $this->map->getSecondaryKeys($uuid ? $uuid->toString() : null);
    }

    /**
     * Returns the UUIDs of all binding descriptors.
     *
     * @return Uuid[] The UUIDs of the stored bindings.
     */
    public function getUuids()
    {
        $uuids = array();

        foreach ($this->map->getPrimaryKeys() as $key) {
            $uuids[] = $this->map->getFirst($key)->getUuid();
        }

        return $uuids;
    }

    /**
     * Returns the contents of the collection as array.
     *
     * @return array[] A multi-dimensional array containing all bindings indexed
     *                 first by UUID, then by package name.
     */
    public function toArray()
    {
        return $this->map->toArray();
    }

    /**
     * Returns whether the collection is empty.
     *
     * @return bool Returns `true` if the collection is empty and `false`
     *              otherwise.
     */
    public function isEmpty()
    {
        return $this->map->isEmpty();
    }
}
