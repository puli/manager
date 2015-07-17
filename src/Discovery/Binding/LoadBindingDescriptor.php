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
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Discovery\Type\BindingTypeDescriptorCollection;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Loads a binding descriptor.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoadBindingDescriptor implements AtomicOperation
{
    /**
     * @var BindingDescriptor
     */
    private $bindingDescriptor;

    /**
     * @var Package
     */
    private $containingPackage;

    /**
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    /**
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var BindingDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingDescriptor $bindingDescriptor, Package $containingPackage, BindingDescriptorCollection $bindingDescriptors, BindingTypeDescriptorCollection $typeDescriptors)
    {
        $this->bindingDescriptor = $bindingDescriptor;
        $this->containingPackage = $containingPackage;
        $this->bindingDescriptors = $bindingDescriptors;
        $this->typeDescriptors = $typeDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // sanity check
        if ($this->bindingDescriptor->isLoaded()) {
            return;
        }

        $typeName = $this->bindingDescriptor->getTypeName();
        $typeDescriptor = $this->typeDescriptors->contains($typeName)
            ? $this->typeDescriptors->getFirst($typeName)
            : null;

        $this->bindingDescriptor->load($this->containingPackage, $typeDescriptor);

        $uuid = $this->bindingDescriptor->getUuid();

        if ($this->bindingDescriptors->contains($uuid)) {
            $this->previousDescriptor = $this->bindingDescriptors->get($uuid);
        }

        $this->bindingDescriptors->add($this->bindingDescriptor);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        // sanity check
        if (!$this->bindingDescriptor->isLoaded()) {
            return;
        }

        // never fails with the check before
        $this->bindingDescriptor->unload();

        if ($this->previousDescriptor) {
            // never fails
            $this->bindingDescriptors->add($this->previousDescriptor);
        } else {
            // never fails
            $this->bindingDescriptors->remove($this->bindingDescriptor->getUuid());
        }
    }
}
