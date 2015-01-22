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
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Transaction\AtomicOperation;

/**
 * Unloads a binding descriptor.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnloadBindingDescriptor implements AtomicOperation
{
    /**
     * @var BindingDescriptor
     */
    private $bindingDescriptor;

    /**
     * @var BindingDescriptorStore
     */
    private $bindingStore;

    /**
     * @var Package
     */
    private $containingPackage;

    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var bool
     */
    private $wasRemoved = false;

    public function __construct(BindingDescriptor $bindingDescriptor, BindingDescriptorStore $bindingStore)
    {
        $this->bindingDescriptor = $bindingDescriptor;
        $this->bindingStore = $bindingStore;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->bindingDescriptor->isLoaded()) {
            return;
        }

        $uuid = $this->bindingDescriptor->getUuid();
        $this->containingPackage = $this->bindingDescriptor->getContainingPackage();
        $this->typeDescriptor = $this->bindingDescriptor->getTypeDescriptor();

        // never fails with the check in the beginning
        $this->bindingDescriptor->unload();

        if ($this->bindingStore->exists($uuid, $this->containingPackage)
            && $this->bindingDescriptor === $this->bindingStore->get($uuid, $this->containingPackage)) {
            // never fails
            $this->bindingStore->remove($uuid, $this->containingPackage);
            $this->wasRemoved = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if (!$this->bindingDescriptor->isLoaded() && $this->containingPackage && $this->typeDescriptor) {
            // never fails with the check before, given that the type name of
            // the description/type didn't changed, which is impossible since
            // they're immutable
            $this->bindingDescriptor->load($this->containingPackage, $this->typeDescriptor);
        }

        if ($this->wasRemoved) {
            // never fails
            $this->bindingStore->add($this->bindingDescriptor, $this->containingPackage);
        }
    }
}
