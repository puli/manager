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
 * Adds a type descriptor to the root module file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddTypeDescriptorToModuleFile implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var BindingTypeDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingTypeDescriptor $typeDescriptor, RootModuleFile $rootModuleFile)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->rootModuleFile = $rootModuleFile;
    }

    public function execute()
    {
        $typeName = $this->typeDescriptor->getTypeName();

        if ($this->rootModuleFile->hasTypeDescriptor($typeName)) {
            $this->previousDescriptor = $this->rootModuleFile->getTypeDescriptor($typeName);
        }

        $this->rootModuleFile->addTypeDescriptor($this->typeDescriptor);
    }

    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootModuleFile->addTypeDescriptor($this->previousDescriptor);
        } else {
            $this->rootModuleFile->removeTypeDescriptor($this->typeDescriptor->getTypeName());
        }
    }
}
