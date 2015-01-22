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

use Puli\Discovery\Api\EditableDiscovery;
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Transaction\AtomicOperation;

/**
 * Defines a type descriptor in the resource discovery.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefineType implements AtomicOperation
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
        $this->discovery->defineType($this->typeDescriptor->toBindingType());
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->discovery->undefineType($this->typeDescriptor->getName());
    }
}
