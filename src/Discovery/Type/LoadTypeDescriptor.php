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
 * Loads a type descriptor.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoadTypeDescriptor implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var Package
     */
    private $containingPackage;

    /**
     * @var BindingTypeDescriptorStore
     */
    private $typeStore;

    /**
     * @var BindingTypeDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingTypeDescriptor $typeDescriptor, Package $containingPackage, BindingTypeDescriptorStore $typeStore)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->containingPackage = $containingPackage;
        $this->typeStore = $typeStore;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->typeDescriptor->isLoaded()) {
            // never fails with the check before
            $this->typeDescriptor->load($this->containingPackage);
        }

        $typeName = $this->typeDescriptor->getName();

        if ($this->typeStore->exists($typeName, $this->containingPackage)) {
            // never fails with the check before
            $this->previousDescriptor = $this->typeStore->get($typeName, $this->containingPackage);
        }

        // never fails
        $this->typeStore->add($this->typeDescriptor, $this->containingPackage);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $typeName = $this->typeDescriptor->getName();

        if ($this->typeDescriptor->isLoaded()) {
            // never fails with the check before
            $this->typeDescriptor->unload();
        }

        if ($this->previousDescriptor) {
            // never fails
            $this->typeStore->add($this->previousDescriptor, $this->containingPackage);
        } else {
            // never fails
            $this->typeStore->remove($typeName, $this->containingPackage);
        }
    }
}
