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

use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Binds a binding descriptor to the resource discovery.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Bind implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $bindingDescriptor;

    /**
     * @var EditableDiscovery
     */
    private $discovery;

    public function __construct(BindingDescriptor $bindingDescriptor, EditableDiscovery $discovery)
    {
        // Clone so that rollback() works if the binding is unloaded
        $this->bindingDescriptor = clone $bindingDescriptor;
        $this->discovery = $discovery;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->discovery->bind(
            $this->bindingDescriptor->getQuery(),
            $this->bindingDescriptor->getTypeName(),
            $this->bindingDescriptor->getParameterValues(),
            $this->bindingDescriptor->getLanguage()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->discovery->unbind(
            $this->bindingDescriptor->getQuery(),
            $this->bindingDescriptor->getTypeName(),
            $this->bindingDescriptor->getParameterValues(),
            $this->bindingDescriptor->getLanguage()
        );
    }
}
