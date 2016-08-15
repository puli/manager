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
use Puli\Discovery\Api\Binding\Binding;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Util\TwoDimensionalHashMap;
use Rhumsaa\Uuid\Uuid;

/**
 * A collection of binding descriptors.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorCollection
{
    /**
     * @var BindingDescriptor[][]
     */
    private $map = array();

    /**
     * Adds a binding descriptor.
     *
     * @param BindingDescriptor $bindingDescriptor The binding descriptor.
     */
    public function add(BindingDescriptor $bindingDescriptor)
    {
        if ($this->contains($bindingDescriptor->getBinding(), $bindingDescriptor->getContainingModule()->getName())) {
            return;
        }

        $this->map[$bindingDescriptor->getContainingModule()->getName()][] = $bindingDescriptor;
    }

    /**
     * Removes a binding descriptor.
     *
     * This method ignores non-existing binding descriptors.
     *
     * @param Binding $binding    The described binding.
     * @param string  $moduleName The name of the module containing the type.
     */
    public function remove(Binding $binding, $moduleName)
    {
        if (!isset($this->map[$moduleName])) {
            return;
        }

        foreach ($this->map[$moduleName] as $key => $bindingDescriptor) {
            if ($bindingDescriptor->getBinding()->equals($binding)) {
                unset($this->map[$moduleName][$key]);

                break;
            }
        }
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
    public function get(Binding $binding, $moduleName)
    {
        if (isset($this->map[$moduleName])) {
            foreach ($this->map[$moduleName] as $bindingDescriptor) {
                if ($bindingDescriptor->getBinding()->equals($binding)) {
                    return $bindingDescriptor;
                }
            }
        }

        throw new OutOfBoundsException(sprintf(
            'The binding %s in module "%s" does not exist.',
            get_class($binding),
            $moduleName
        ));
    }

    /**
     * Returns whether a binding descriptor exists.
     *
     * @param Uuid $uuid The UUID of the binding descriptor.
     *
     * @return bool Returns `true` if a binding descriptor was set for the given
     *              UUID.
     */
    public function contains(Binding $binding, $moduleName = null)
    {
        if (null === $moduleName) {
            foreach ($this->map as $bindingDescriptors) {
                foreach ($bindingDescriptors as $key => $bindingDescriptor) {
                    if ($bindingDescriptor->getBinding()->equals($binding)) {
                        return true;
                    }
                }
            }

            return false;
        }

        if (!isset($this->map[$moduleName])) {
            return false;
        }

        foreach ($this->map[$moduleName] as $key => $bindingDescriptor) {
            if ($bindingDescriptor->getBinding()->equals($binding)) {
                return true;
            }
        }

        return false;
    }

    public function listByBinding(Binding $binding)
    {
        $bindingDescriptors = array();

        foreach ($this->map as $bindingDescriptors) {
            foreach ($bindingDescriptors as $bindingDescriptor) {
                if ($bindingDescriptor->getBinding()->equals($binding)) {
                    $bindingDescriptors[] = $bindingDescriptor;
                }
            }
        }

        return $bindingDescriptors;
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
