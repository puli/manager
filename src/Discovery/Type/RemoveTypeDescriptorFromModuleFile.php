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

use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Removes a type descriptor from the root module file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RemoveTypeDescriptorFromModuleFile implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeName;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var BindingTypeDescriptor
     */
    private $previousDescriptor;

    public function __construct($typeName, RootModuleFile $rootModuleFile)
    {
        $this->typeName = $typeName;
        $this->rootModuleFile = $rootModuleFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->rootModuleFile->hasTypeDescriptor($this->typeName)) {
            return;
        }

        $this->previousDescriptor = $this->rootModuleFile->getTypeDescriptor($this->typeName);
        $this->rootModuleFile->removeTypeDescriptor($this->typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootModuleFile->addTypeDescriptor($this->previousDescriptor);
        }
    }
}
