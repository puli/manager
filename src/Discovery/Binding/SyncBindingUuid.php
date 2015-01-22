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

use LogicException;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Transaction\AtomicOperation;
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
     * @var BindingDescriptorStore
     */
    private $bindingStore;

    /**
     * @var bool
     */
    private $snapshotTaken = false;

    public function __construct(Uuid $uuid, EditableDiscovery $discovery, BindingDescriptorStore $bindingStore)
    {
        $this->uuid = $uuid;
        $this->discovery = $discovery;
        $this->bindingStore = $bindingStore;
    }

    /**
     * Records whether the UUID is currently enabled.
     */
    public function takeSnapshot()
    {
        $bindingDescriptor = $this->bindingStore->getEnabled($this->uuid);

        // Clone so that rollback() works if the binding is unloaded
        $this->enabledBindingBefore = $bindingDescriptor ? clone $bindingDescriptor : null;
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
        $bindingDescriptor = $this->bindingStore->getEnabled($this->uuid);

        // Clone so that rollback() works if the binding is unloaded
        $this->enabledBindingAfter = $bindingDescriptor ? clone $bindingDescriptor : null;

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
