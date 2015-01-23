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
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
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
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var BindingTypeDescriptor|null
     */
    private $enabledTypeBefore;

    /**
     * @var BindingTypeDescriptor|null
     */
    private $enabledTypeAfter;

    /**
     * @var bool
     */
    private $snapshotTaken = false;

    public function __construct($typeName, EditableDiscovery $discovery, BindingTypeDescriptorCollection $typeDescriptors)
    {
        $this->typeName = $typeName;
        $this->discovery = $discovery;
        $this->typeDescriptors = $typeDescriptors;
    }

    /**
     * Records whether the type name is currently enabled.
     */
    public function takeSnapshot()
    {
        $this->enabledTypeBefore = $this->typeDescriptors->getEnabled($this->typeName);
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
        $this->enabledTypeAfter = $this->typeDescriptors->getEnabled($this->typeName);

        $this->syncTypeName($this->enabledTypeBefore, $this->enabledTypeAfter);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->syncTypeName($this->enabledTypeAfter, $this->enabledTypeBefore);
    }

    private function syncTypeName(BindingTypeDescriptor $enabledTypeBefore = null, BindingTypeDescriptor $enabledTypeAfter = null)
    {
        if ($enabledTypeBefore && !$enabledTypeAfter) {
            $this->discovery->undefineType($this->typeName);
        } elseif (!$enabledTypeBefore && $enabledTypeAfter) {
            $this->discovery->defineType($enabledTypeAfter->toBindingType());
        } elseif ($enabledTypeBefore !== $enabledTypeAfter) {
            $this->discovery->undefineType($this->typeName);
            $this->discovery->defineType($enabledTypeAfter->toBindingType());
        }
    }
}
