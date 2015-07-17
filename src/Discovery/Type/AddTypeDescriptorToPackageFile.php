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
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Adds a type descriptor to the root package file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddTypeDescriptorToPackageFile implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var BindingTypeDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingTypeDescriptor $typeDescriptor, RootPackageFile $rootPackageFile)
    {
        $this->typeDescriptor = $typeDescriptor;
        $this->rootPackageFile = $rootPackageFile;
    }

    public function execute()
    {
        $typeName = $this->typeDescriptor->getName();

        if ($this->rootPackageFile->hasTypeDescriptor($typeName)) {
            $this->previousDescriptor = $this->rootPackageFile->getTypeDescriptor($typeName);
        }

        $this->rootPackageFile->addTypeDescriptor($this->typeDescriptor);
    }

    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootPackageFile->addTypeDescriptor($this->previousDescriptor);
        } else {
            $this->rootPackageFile->removeTypeDescriptor($this->typeDescriptor->getName());
        }
    }
}
