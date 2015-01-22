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
use Puli\RepositoryManager\Discovery\Type\BindingTypeDescriptorStore;
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
     * @var BindingDescriptorStore
     */
    private $bindingStore;

    /**
     * @var BindingTypeDescriptorStore
     */
    private $typeStore;

    /**
     * @var BindingDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingDescriptor $bindingDescriptor, Package $containingPackage, BindingDescriptorStore $bindingStore, BindingTypeDescriptorStore $typeStore)
    {
        $this->bindingDescriptor = $bindingDescriptor;
        $this->containingPackage = $containingPackage;
        $this->bindingStore = $bindingStore;
        $this->typeStore = $typeStore;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $typeName = $this->bindingDescriptor->getTypeName();
        $uuid = $this->bindingDescriptor->getUuid();

        if ($this->bindingStore->exists($uuid, $this->containingPackage)) {
            $this->previousDescriptor = $this->bindingStore->get($uuid, $this->containingPackage);
        }

        $this->bindingStore->add($this->bindingDescriptor, $this->containingPackage);

        if (!$this->bindingDescriptor->isLoaded()) {
            $typeDescriptor = $this->typeStore->existsAny($typeName)
                ? $this->typeStore->get($typeName)
                : null;

            $this->bindingDescriptor->load($this->containingPackage, $typeDescriptor);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $uuid = $this->bindingDescriptor->getUuid();

        if ($this->bindingDescriptor->isLoaded()) {
            // never fails with the check before
            $this->bindingDescriptor->unload();
        }

        if ($this->previousDescriptor) {
            // never fails
            $this->bindingStore->add($this->previousDescriptor, $this->containingPackage);
        } else {
            // never fails
            $this->bindingStore->remove($uuid, $this->containingPackage);
        }
    }
}
