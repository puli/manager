<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Type;

use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Transaction\AtomicOperation;

/**
 * Unloads a type descriptor.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnloadTypeDescriptor implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var BindingTypeDescriptorStore
     */
    private $typeStore;

    /**
     * @var Package
     */
    private $containingPackage;

    /**
     * @var bool
     */
    private $wasRemoved = false;

    public function __construct($typeDescriptor, BindingTypeDescriptorStore $typeStore)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->typeStore = $typeStore;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $typeName = $this->typeDescriptor->getName();

        if ($this->typeDescriptor->isLoaded()) {
            // never fails with the check before
            $this->containingPackage = $this->typeDescriptor->getContainingPackage();

            // never fails with the check before
            $this->typeDescriptor->unload();
        }

        if ($this->typeStore->exists($typeName, $this->containingPackage)
            && $this->typeDescriptor === $this->typeStore->get($typeName, $this->containingPackage)) {
            // never fails
            $this->typeStore->remove($typeName, $this->containingPackage);
            $this->wasRemoved = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if (!$this->typeDescriptor->isLoaded() && $this->containingPackage) {
            // never fails with the check before
            $this->typeDescriptor->load($this->containingPackage);
        }

        if ($this->wasRemoved) {
            // never fails
            $this->typeStore->add($this->typeDescriptor, $this->containingPackage);
        }
    }
}
