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

use LogicException;
use Puli\Discovery\Api\Binding\Binding;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Transaction\AtomicOperation;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expr;

/**
 * Synchronizes a binding descriptor with the discovery.
 *
 * The method {@link takeSnapshot()} must be called before executing the
 * operation. This method will record whether the binding descriptor is currently
 * enabled (i.e. loaded in the discovery).
 *
 * Once the operation is executed, another snapshot is taken. If the snapshots
 * differ, the binding descriptor is then either added to or removed from the
 * discovery, depending on the outcome.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SyncBinding implements AtomicOperation
{
    /**
     * @var Binding
     */
    private $binding;

    /**
     * @var EditableDiscovery
     */
    private $discovery;

    /**
     * @var BindingDescriptor
     */
    private $enabledBindingBefore;

    /**
     * @var BindingDescriptor
     */
    private $enabledBindingAfter;

    /**
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    /**
     * @var bool
     */
    private $snapshotTaken = false;

    public function __construct(Binding $binding, EditableDiscovery $discovery, BindingDescriptorCollection $bindingDescriptors)
    {
        $this->binding = $binding;
        $this->discovery = $discovery;
        $this->bindingDescriptors = $bindingDescriptors;
    }

    /**
     * Records whether the UUID is currently enabled.
     */
    public function takeSnapshot()
    {
        $this->enabledBindingBefore = null;

        foreach ($this->bindingDescriptors->listByBinding($this->binding) as $bindingDescriptor) {
            if ($bindingDescriptor->isEnabled()) {
                // Clone so that rollback() works if the binding is unloaded
                $this->enabledBindingBefore = clone $bindingDescriptor;
            }
        }

        $this->snapshotTaken = true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->snapshotTaken) {
            throw new LogicException('takeSnapshot() was not called');
        }

        // Remember for rollback()
        $this->enabledBindingAfter = null;

        foreach ($this->bindingDescriptors->listByBinding($this->binding) as $bindingDescriptor) {
            if ($bindingDescriptor->isEnabled()) {
                // Clone so that rollback() works if the binding is unloaded
                $this->enabledBindingAfter = clone $bindingDescriptor;
            }
        }

        $this->syncBinding($this->enabledBindingBefore, $this->enabledBindingAfter);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->syncBinding($this->enabledBindingAfter, $this->enabledBindingBefore);
    }

    private function syncBinding(BindingDescriptor $enabledBefore = null, BindingDescriptor $enabledAfter = null)
    {
        if (!$enabledBefore && $enabledAfter) {
            $this->discovery->addBinding($this->binding);
        } elseif ($enabledBefore && !$enabledAfter) {
            $this->discovery->removeBindings(
                $this->binding->getTypeName(),
                Expr::method('equals', $this->binding, Expr::same(true))
            );
//        } elseif ($enabledBefore && $enabledAfter && !$enabledBefore->getBinding()->equals($enabledAfter->getBinding())) {
//            $this->discovery->removeBindings(
//                $enabledBefore->getTypeName(),
//                Expr::method('equals', $enabledBefore->getBinding(), Expr::same(true))
//            );
//            $this->discovery->addBinding($enabledAfter->getBinding());
        }
    }
}
