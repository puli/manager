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
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var BindingTypeDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingTypeDescriptor $typeDescriptor, Package $containingPackage, BindingTypeDescriptorCollection $types)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->containingPackage = $containingPackage;
        $this->typeDescriptors = $types;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // sanity check
        if ($this->typeDescriptor->isLoaded()) {
            return;
        }

        // never fails with the check before
        $this->typeDescriptor->load($this->containingPackage);

        $typeName = $this->typeDescriptor->getName();
        $packageName = $this->containingPackage->getName();

        if ($this->typeDescriptors->contains($typeName, $packageName)) {
            // never fails with the check before
            $this->previousDescriptor = $this->typeDescriptors->get($typeName, $packageName);
        }

        // never fails
        $this->typeDescriptors->add($this->typeDescriptor);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        // sanity check
        if (!$this->typeDescriptor->isLoaded()) {
            return;
        }

        $typeName = $this->typeDescriptor->getName();

        // never fails with the check before
        $this->typeDescriptor->unload();

        if ($this->previousDescriptor && $this->previousDescriptor->isLoaded()) {
            // never fails
            $this->typeDescriptors->add($this->previousDescriptor);
        } else {
            // never fails
            $this->typeDescriptors->remove($typeName, $this->containingPackage->getName());
        }
    }
}
