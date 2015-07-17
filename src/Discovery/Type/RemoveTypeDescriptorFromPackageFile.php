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
 * Removes a type descriptor from the root package file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RemoveTypeDescriptorFromPackageFile implements AtomicOperation
{
    /**
     * @var BindingTypeDescriptor
     */
    private $typeName;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var BindingTypeDescriptor
     */
    private $previousDescriptor;

    public function __construct($typeName, RootPackageFile $rootPackageFile)
    {
        $this->typeName = $typeName;
        $this->rootPackageFile = $rootPackageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->rootPackageFile->hasTypeDescriptor($this->typeName)) {
            return;
        }

        $this->previousDescriptor = $this->rootPackageFile->getTypeDescriptor($this->typeName);
        $this->rootPackageFile->removeTypeDescriptor($this->typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootPackageFile->addTypeDescriptor($this->previousDescriptor);
        }
    }
}
