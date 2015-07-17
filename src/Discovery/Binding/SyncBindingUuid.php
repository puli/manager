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
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Transaction\AtomicOperation;
use Rhumsaa\Uuid\Uuid;

/**
 * Synchronizes a binding descriptor UUID with the discovery.
 *
 * The method {@link takeSnapshot()} must be called before executing the
 * operation. This method will record whether a binding descriptor is currently
 * enabled (i.e. loaded in the discovery) for that UUID.
 *
 * Once the operation is executed, another snapshot is taken. If the snapshots
 * differ, the binding descriptor for the UUID is then either bound to or
 * unbound from the discovery, depending on the outcome.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SyncBindingUuid implements AtomicOperation
{
    /**
     * @var Uuid
     */
    private $uuid;

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

    public function __construct(Uuid $uuid, EditableDiscovery $discovery, BindingDescriptorCollection $bindingDescriptors)
    {
        $this->uuid = $uuid;
        $this->discovery = $discovery;
        $this->bindingDescriptors = $bindingDescriptors;
    }

    /**
     * Records whether the UUID is currently enabled.
     */
    public function takeSnapshot()
    {
        $this->enabledBindingBefore = null;

        if ($this->bindingDescriptors->contains($this->uuid)) {
            $bindingDescriptor = $this->bindingDescriptors->get($this->uuid);

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

        if ($this->bindingDescriptors->contains($this->uuid)) {
            $bindingDescriptor = $this->bindingDescriptors->get($this->uuid);

            if ($bindingDescriptor->isEnabled()) {
                // Clone so that rollback() works if the binding is unloaded
                $this->enabledBindingAfter = clone $bindingDescriptor;
            }
        }

        $this->syncBindingUuid($this->enabledBindingBefore, $this->enabledBindingAfter);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->syncBindingUuid($this->enabledBindingAfter, $this->enabledBindingBefore);
    }

    private function syncBindingUuid(BindingDescriptor $enabledBefore = null, BindingDescriptor $enabledAfter = null)
    {
        if (!$enabledBefore && $enabledAfter) {
            $this->discovery->bind(
                $enabledAfter->getQuery(),
                $enabledAfter->getTypeName(),
                $enabledAfter->getParameterValues(),
                $enabledAfter->getLanguage()
            );
        } elseif ($enabledBefore && !$enabledAfter) {
            $this->discovery->unbind(
                $enabledBefore->getQuery(),
                $enabledBefore->getTypeName(),
                $enabledBefore->getParameterValues(),
                $enabledBefore->getLanguage()
            );
        }
    }
}
