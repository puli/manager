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

use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Discovery\Type\BindingTypeDescriptorCollection;
use Puli\Manager\Transaction\OperationInterceptor;

/**
 * Base class for reload operations.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractReloadBindingDescriptors implements OperationInterceptor
{
    /**
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var BindingDescriptor[]
     */
    private $reloadedDescriptors = array();

    /**
     * @param BindingTypeDescriptorCollection $typeDescriptors
     */
    public function __construct(BindingTypeDescriptorCollection $typeDescriptors)
    {
        $this->typeDescriptors = $typeDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postRollback()
    {
        foreach ($this->reloadedDescriptors as $bindingDescriptor) {
            $this->reloadBindingDescriptor($bindingDescriptor);
        }
    }

    /**
     * Unloads and loads a binding descriptor.
     *
     * The descriptor is remembered and reloaded again in {@link postRollback()}
     * if the intercepted operation needs to be rolled back.
     *
     * @param BindingDescriptor $bindingDescriptor The descriptor to reload.
     */
    protected function reloadBindingDescriptor(BindingDescriptor $bindingDescriptor)
    {
        if (!$bindingDescriptor->isLoaded()) {
            return;
        }

        // Keep backup of containing package before calling unload()
        $containingPackage = $bindingDescriptor->getContainingPackage();
        $typeName = $bindingDescriptor->getTypeName();
        $typeDescriptor = $this->typeDescriptors->getEnabled($typeName);

        // never fails with the check in the beginning
        $bindingDescriptor->unload();

        // never fails after unloading, given that the type name matches
        // (which we can guarantee here)
        $bindingDescriptor->load($containingPackage, $typeDescriptor);

        $this->reloadedDescriptors[] = $bindingDescriptor;
    }
}
