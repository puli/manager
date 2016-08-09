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

use Puli\Discovery\Api\Binding\Binding;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Transaction\AtomicOperation;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expr;

/**
 * Removes a binding descriptor from the root module file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RemoveBindingDescriptorFromModuleFile implements AtomicOperation
{
    /**
     * @var Binding
     */
    private $binding;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var BindingDescriptor
     */
    private $previousDescriptor;

    public function __construct(Binding $binding, RootModuleFile $rootModuleFile)
    {
        $this->binding = $binding;
        $this->rootModuleFile = $rootModuleFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $expr = Expr::method('getBinding', Expr::method('equals', $this->binding, Expr::same(true)));
        $matchingDescriptors = $this->rootModuleFile->findBindingDescriptors($expr);

        if (0 === count($matchingDescriptors)) {
            return;
        }

        $this->previousDescriptor = current($matchingDescriptors);
        $this->rootModuleFile->removeBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootModuleFile->addBindingDescriptor($this->previousDescriptor);
        }
    }
}
