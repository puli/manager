<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Discovery\Binding;

use OutOfBoundsException;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Rhumsaa\Uuid\Uuid;

/**
 * A collection of binding descriptors.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorCollection
{
    /**
     * @var BindingDescriptor[]
     */
    private $map;

    /**
     * Creates the store.
     */
    public function __construct()
    {
        $this->map = array();
    }

    /**
     * Adds a binding descriptor.
     *
     * @param BindingDescriptor $bindingDescriptor The binding descriptor.
     */
    public function add(BindingDescriptor $bindingDescriptor)
    {
        $this->map[$bindingDescriptor->getUuid()->toString()] = $bindingDescriptor;
    }

    /**
     * Removes a binding descriptor.
     *
     * This method ignores non-existing binding descriptors.
     *
     * @param Uuid   $uuid        The UUID of the binding descriptor.
     */
    public function remove(Uuid $uuid)
    {
        unset($this->map[$uuid->toString()]);
    }

    /**
     * Returns a binding descriptor.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return BindingDescriptor The binding descriptor.
     *
     * @throws OutOfBoundsException If no binding descriptor was set for the
     *                              given UUID.
     */
    public function get(Uuid $uuid)
    {
        if (!isset($this->map[$uuid->toString()])) {
            throw new OutOfBoundsException(sprintf(
                'The binding with UUID "%s" does not exist.',
                $uuid->toString()
            ));
        }

        return $this->map[$uuid->toString()];
    }

    /**
     * Returns whether a binding descriptor exists.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return bool Returns `true` if a binding descriptor was set for the given
     *              UUID.
     */
    public function contains(Uuid $uuid)
    {
        return isset($this->map[$uuid->toString()]);
    }

    /**
     * Returns the UUIDs of all binding descriptors.
     *
     * @return Uuid[] The UUIDs of the stored bindings.
     */
    public function getUuids()
    {
        $uuids = array();

        foreach ($this->map as $bindingDescriptor) {
            $uuids[] = $bindingDescriptor->getUuid();
        }

        return $uuids;
    }

    /**
     * Returns the contents of the collection as array.
     *
     * @return BindingDescriptor[] An array containing all bindings indexed by UUID.
     */
    public function toArray()
    {
        return $this->map;
    }

    /**
     * Returns whether the collection is empty.
     *
     * @return bool Returns `true` if the collection is empty and `false`
     *              otherwise.
     */
    public function isEmpty()
    {
        return 0 === count($this->map);
    }
}
