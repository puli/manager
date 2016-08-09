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
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Unloads a binding descriptor.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnloadBindingDescriptor implements AtomicOperation
{
    /**
     * @var BindingDescriptor
     */
    private $bindingDescriptor;

    /**
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    /**
     * @var Module
     */
    private $containingModule;

    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var bool
     */
    private $wasRemoved = false;

    public function __construct(BindingDescriptor $bindingDescriptor, BindingDescriptorCollection $bindingDescriptors)
    {
        $this->bindingDescriptor = $bindingDescriptor;
        $this->bindingDescriptors = $bindingDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // sanity check
        if (!$this->bindingDescriptor->isLoaded()) {
            return;
        }

        $this->containingModule = $this->bindingDescriptor->getContainingModule();
        $this->typeDescriptor = $this->bindingDescriptor->getTypeDescriptor();

        $binding = $this->bindingDescriptor->getBinding();
        $moduleName = $this->containingModule->getName();

        // never fails with the check in the beginning
        $this->bindingDescriptor->unload();

        if ($this->bindingDescriptors->contains($binding, $moduleName)
            && $this->bindingDescriptor === $this->bindingDescriptors->get($binding, $moduleName)) {
            // never fails
            $this->bindingDescriptors->remove($binding, $moduleName);
            $this->wasRemoved = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->bindingDescriptor->isLoaded() || !$this->containingModule || !$this->typeDescriptor) {
            return;
        }

        // never fails with the check before, given that the type name of
        // the description/type didn't changed, which is impossible since
        // they're immutable
        $this->bindingDescriptor->load($this->containingModule, $this->typeDescriptor);

        if ($this->wasRemoved) {
            // never fails
            $this->bindingDescriptors->add($this->bindingDescriptor);
        }
    }
}
