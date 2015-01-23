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
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var Package
     */
    private $containingPackage;

    /**
     * @var bool
     */
    private $wasRemoved = false;

    public function __construct($typeDescriptor, BindingTypeDescriptorCollection $typeDescriptors)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->typeDescriptors = $typeDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // sanity check
        if (!$this->typeDescriptor->isLoaded()) {
            return;
        }


        // never fails with the check before
        $this->containingPackage = $this->typeDescriptor->getContainingPackage();

        $typeName = $this->typeDescriptor->getName();
        $packageName = $this->containingPackage->getName();

        // never fails with the check before
        $this->typeDescriptor->unload();

        if ($this->typeDescriptors->contains($typeName, $packageName)
            && $this->typeDescriptor === $this->typeDescriptors->get($typeName, $packageName)) {
            // never fails
            $this->typeDescriptors->remove($typeName, $packageName);
            $this->wasRemoved = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        // sanity check
        if ($this->typeDescriptor->isLoaded() || !$this->containingPackage) {
            return;
        }

        // never fails with the check before
        $this->typeDescriptor->load($this->containingPackage);

        if ($this->wasRemoved) {
            // never fails
            $this->typeDescriptors->add($this->typeDescriptor);
        }
    }
}
