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

use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Discovery\Type\BindingTypeDescriptorCollection;
use Puli\RepositoryManager\Transaction\AtomicOperation;

/**
 * Loads a binding descriptor.
 *
 * @since  1.0
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
            ? $this->typeDescriptors->get($typeName)
            : null;

        $this->bindingDescriptor->load($this->containingPackage, $typeDescriptor);

        $uuid = $this->bindingDescriptor->getUuid();
        $packageName = $this->containingPackage->getName();

        if ($this->bindingDescriptors->contains($uuid, $packageName)) {
            $this->previousDescriptor = $this->bindingDescriptors->get($uuid, $packageName);
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
            $this->bindingDescriptors->remove($this->bindingDescriptor->getUuid(), $this->containingPackage->getName());
        }
    }
}
