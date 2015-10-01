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

use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Defines a type descriptor in the resource discovery.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddBindingType implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var EditableDiscovery
     */
    private $discovery;

    public function __construct(BindingTypeDescriptor $typeDescriptor, EditableDiscovery $discovery)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->discovery = $discovery;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->discovery->addBindingType($this->typeDescriptor->getType());
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->discovery->removeBindingType($this->typeDescriptor->getTypeName());
    }
}
