<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Discovery\Type;

use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Loads a type descriptor.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoadTypeDescriptor implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var Module
     */
    private $containingModule;

    /**
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var BindingTypeDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingTypeDescriptor $typeDescriptor, Module $containingModule, BindingTypeDescriptorCollection $types)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->containingModule = $containingModule;
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
        $this->typeDescriptor->load($this->containingModule);

        $typeName = $this->typeDescriptor->getTypeName();
        $moduleName = $this->containingModule->getName();

        if ($this->typeDescriptors->contains($typeName, $moduleName)) {
            // never fails with the check before
            $this->previousDescriptor = $this->typeDescriptors->get($typeName, $moduleName);
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

        $typeName = $this->typeDescriptor->getTypeName();

        // never fails with the check before
        $this->typeDescriptor->unload();

        if ($this->previousDescriptor && $this->previousDescriptor->isLoaded()) {
            // never fails
            $this->typeDescriptors->add($this->previousDescriptor);
        } else {
            // never fails
            $this->typeDescriptors->remove($typeName, $this->containingModule->getName());
        }
    }
}
