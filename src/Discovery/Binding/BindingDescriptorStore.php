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
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Util\TwoDimensionalHashMap;
use Rhumsaa\Uuid\Uuid;

/**
 * A store for binding descriptors.
 *
 * Each descriptor has a composite key:
 *
 *  * The UUID of the binding descriptor.
 *  * The package that defines the binding.
 *
 * The store implements transparent merging of bindings defined within different
 * packages, but with the same UUID. If a binding is requested for a UUID
 * without giving a package name, the first binding set for that UUID is
 * returned by {@link get()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorStore
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
     * @param Package           $package           The package defining the
     *                                             binding descriptor.
     */
    public function  add(BindingDescriptor $bindingDescriptor, Package $package)
    {
        $this->map->set($bindingDescriptor->getUuid()->toString(), $package->getName(), $bindingDescriptor);
    }

    /**
     * Removes a binding descriptor.
     *
     * This method ignores non-existing binding descriptors.
     *
     * @param Uuid    $uuid    The UUID of the binding descriptor.
     * @param Package $package The package containing the descriptor.
     */
    public function remove(Uuid $uuid, Package $package)
    {
        $this->map->remove($uuid->toString(), $package->getName());
    }

    /**
     * Returns a binding descriptor.
     *
     * If no package is passed, the first descriptor set for the UUID is
     * returned.
     *
     * @param Uuid    $uuid    The UUID of the binding descriptor.
     * @param Package $package The package containing the descriptor.
     *
     * @return BindingDescriptor The binding descriptor.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID/package.
     */
    public function get(Uuid $uuid, Package $package = null)
    {
        if (null !== $package) {
            return $this->map->get($uuid->toString(), $package->getName());
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
        if (!$this->existsAny($uuid)) {
            return null;
        }

        foreach ($this->getAll($uuid) as $bindingDescriptor) {
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
    public function getAll(Uuid $uuid)
    {
        return $this->map->listByPrimaryKey($uuid->toString());
    }

    /**
     * Returns whether a binding descriptor was set for the given UUID/package.
     *
     * @param Uuid    $uuid    The UUID of the binding descriptor.
     * @param Package $package The package containing the descriptor.
     *
     * @return bool Returns `true` if a binding descriptor was set for the given
     *              UUID/package.
     */
    public function exists(Uuid $uuid, Package $package)
    {
        return $this->map->contains($uuid->toString(), $package->getName());
    }

    /**
     * Returns whether a binding descriptor was set for the given UUID in any
     * package.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return bool Returns `true` if a binding descriptor was set for the given
     *              UUID.
     */
    public function existsAny(Uuid $uuid)
    {
        return $this->map->contains($uuid->toString());
    }

    /**
     * Returns whether an enabled binding descriptor was set for the given UUID
     * in any package.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return bool Returns `true` if an enabled binding descriptor was set for
     *              the given UUID.
     */
    public function existsEnabled(Uuid $uuid)
    {
        try {
            foreach ($this->getAll($uuid) as $bindingDescriptor) {
                if ($bindingDescriptor->isEnabled()) {
                    return true;
                }
            }
        } catch (OutOfBoundsException $e) {
        }

        return false;
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
    public function getDefiningPackageNames(Uuid $uuid)
    {
        return $this->map->getSecondaryKeys($uuid->toString());
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
}
