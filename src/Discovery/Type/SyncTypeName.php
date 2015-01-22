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

use LogicException;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\RepositoryManager\Transaction\AtomicOperation;

/**
 * Synchronizes a type name with the discovery.
 *
 * The method {@link takeSnapshot()} must be called before executing the
 * operation. This method will record whether the type name is currently
 * enabled (i.e. loaded in the discovery).
 *
 * Once the operation is executed, another snapshot is taken. If the snapshots
 * differ, type is either defined or undefined in the discovery, depending on
 * the outcome.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SyncTypeName implements AtomicOperation
{
    /**
     * @var string
     */
    private $typeName;

    /**
     * @var EditableDiscovery
     */
    private $discovery;

    /**
     * @var bool
     */
    private $wasEnabled;

    /**
     * @var bool
     */
    private $isEnabled;

    /**
     * @var BindingTypeDescriptorStore
     */
    private $typeStore;

    public function __construct($typeName, EditableDiscovery $discovery, BindingTypeDescriptorStore $typeStore)
    {
        $this->typeName = $typeName;
        $this->discovery = $discovery;
        $this->typeStore = $typeStore;
    }

    /**
     * Records whether the type name is currently enabled.
     */
    public function takeSnapshot()
    {
        $this->wasEnabled = $this->typeStore->existsEnabled($this->typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (null === $this->wasEnabled) {
            throw new LogicException('takeSnapshot() was not called');
        }

        // Remember for rollback()
        $this->isEnabled = $this->typeStore->existsEnabled($this->typeName);

        $this->syncTypeName($this->wasEnabled, $this->isEnabled);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->syncTypeName($this->isEnabled, $this->wasEnabled);
    }

    private function syncTypeName($wasEnabled, $isEnabled)
    {
        if ($wasEnabled && !$isEnabled) {
            $this->discovery->undefineType($this->typeName);
        } elseif (!$wasEnabled && $isEnabled) {
            $bindingType = $this->typeStore->get($this->typeName);
            $this->discovery->defineType($bindingType->toBindingType());
        }
    }
}
