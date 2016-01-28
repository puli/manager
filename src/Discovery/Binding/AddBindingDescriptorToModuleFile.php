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

/**
 * Adds a binding descriptor to the root module file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddBindingDescriptorToModuleFile implements AtomicOperation
{
    /**
     * @var BindingDescriptor
     */
    private $bindingDescriptor;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var BindingDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingDescriptor $bindingDescriptor, RootModuleFile $rootModuleFile)
    {
        $this->bindingDescriptor = $bindingDescriptor;
        $this->rootModuleFile = $rootModuleFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $uuid = $this->bindingDescriptor->getUuid();

        if ($this->rootModuleFile->hasBindingDescriptor($uuid)) {
            $this->previousDescriptor = $this->rootModuleFile->getBindingDescriptor($uuid);
        }

        $this->rootModuleFile->addBindingDescriptor($this->bindingDescriptor);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootModuleFile->addBindingDescriptor($this->previousDescriptor);
        } else {
            $this->rootModuleFile->removeBindingDescriptor($this->bindingDescriptor->getUuid());
        }
    }
}
