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

use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Transaction\AtomicOperation;
use Rhumsaa\Uuid\Uuid;

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
     * @var Uuid
     */
    private $uuid;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var BindingDescriptor
     */
    private $previousDescriptor;

    public function __construct(Uuid $uuid, RootModuleFile $rootModuleFile)
    {
        $this->uuid = $uuid;
        $this->rootModuleFile = $rootModuleFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->rootModuleFile->hasBindingDescriptor($this->uuid)) {
            return;
        }

        $this->previousDescriptor = $this->rootModuleFile->getBindingDescriptor($this->uuid);
        $this->rootModuleFile->removeBindingDescriptor($this->uuid);
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
