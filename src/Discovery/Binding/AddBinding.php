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
use Puli\Manager\Transaction\AtomicOperation;
use Webmozart\Expression\Expr;

/**
 * Binds a binding descriptor to the resource discovery.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddBinding implements AtomicOperation
{
    /**
     * @var BindingDescriptor
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
        $this->discovery->addBinding($this->bindingDescriptor->getBinding());
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->discovery->removeBindings(
            $this->bindingDescriptor->getTypeName(),
            Expr::method('equals', $this->bindingDescriptor->getBinding(), Expr::same(true))
        );
    }
}
