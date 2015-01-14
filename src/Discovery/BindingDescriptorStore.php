<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery;

use OutOfBoundsException;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Util\CompositeKeyStore;
use Rhumsaa\Uuid\Uuid;

/**
 * A store for binding descriptors.
 *
 * Each descriptor has a composite key:
 *
 *  * The UUID of the binding.
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
     * @var CompositeKeyStore
     */
    private $store;

    /**
     * Creates the store.
     */
    public function __construct()
    {
        $this->store = new CompositeKeyStore();
    }

    /**
     * Adds a binding descriptor.
     *
     * @param BindingDescriptor $bindingDescriptor The binding descriptor.
     * @param Package           $package           The package defining the
     *                                             binding descriptor.
     */
    public function add(BindingDescriptor $bindingDescriptor, Package $package)
    {
        $this->store->set($bindingDescriptor->getUuid()->toString(), $package->getName(), $bindingDescriptor);
    }

    /**
     * Removes a binding descriptor.
     *
     * This method ignores non-existing binding descriptors.
     *
     * @param Uuid    $uuid    The UUID of the binding.
     * @param Package $package The package containing the binding.
     */
    public function remove(Uuid $uuid, Package $package)
    {
        $this->store->remove($uuid->toString(), $package->getName());
    }

    /**
     * Returns a binding descriptor.
     *
     * If no package is passed, the first descriptor set for the UUID is
     * returned.
     *
     * @param Uuid    $uuid    The UUID of the binding.
     * @param Package $package The package containing the binding.
     *
     * @return BindingDescriptor The binding descriptor.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID/package.
     */
    public function get(Uuid $uuid, Package $package = null)
    {
        if (null !== $package) {
            return $this->store->get($uuid->toString(), $package->getName());
        }

        return $this->store->getFirst($uuid->toString());
    }

    /**
     * Returns all binding descriptors set for the given UUID.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return BindingDescriptor[] The binding descriptors.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID.
     */
    public function getAll(Uuid $uuid)
    {
        return $this->store->getAll($uuid->toString());
    }

    /**
     * Returns whether a binding descriptor was set for the given UUID/package.
     *
     * @param Uuid    $uuid    The UUID of the binding.
     * @param Package $package The package containing the binding.
     *
     * @return bool Returns `true` if a binding descriptor was set for the given
     *              UUID/package.
     */
    public function exists(Uuid $uuid, Package $package)
    {
        return $this->store->contains($uuid->toString(), $package->getName());
    }

    /**
     * Returns whether a binding descriptor was set for the given UUID in any
     * package.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return bool Returns `true` if a binding descriptor was set for the given
     *              UUID.
     */
    public function existsAny(Uuid $uuid)
    {
        return $this->store->contains($uuid->toString());
    }

    /**
     * Returns whether an enabled binding descriptor was set for the given UUID
     * in any package.
     *
     * @param Uuid $uuid The UUID of the binding.
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
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return string[] The package names.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID.
     */
    public function getDefiningPackageNames(Uuid $uuid)
    {
        return $this->store->getSecondaryKeys($uuid->toString());
    }

    /**
     * Returns whether more than one binding descriptor was set for the given
     * UUID.
     *
     * @param Uuid $uuid The UUID of the binding.
     *
     * @return bool Returns `true` if more than one binding was set for the
     *              UUID, `false` otherwise.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID.
     */
    public function isDuplicate(Uuid $uuid)
    {
        return $this->store->getCount($uuid->toString()) > 1;
    }

    /**
     * Returns the UUIDs of all binding descriptors.
     *
     * @return Uuid[] The UUIDs of the stored bindings.
     */
    public function getUuids()
    {
        $uuids = array();

        foreach ($this->store->getPrimaryKeys() as $key) {
            $uuids[] = $this->store->getFirst($key)->getUuid();
        }

        return $uuids;
    }
}
