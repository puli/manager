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
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Transaction\AtomicOperation;

/**
 * Adds a binding descriptor to the root package file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AddBindingDescriptorToPackageFile implements AtomicOperation
{
    /**
     * @var BindingDescriptor
     */
    private $bindingDescriptor;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var BindingDescriptor
     */
    private $previousDescriptor;

    public function __construct(BindingDescriptor $bindingDescriptor, RootPackageFile $rootPackageFile)
    {
        $this->bindingDescriptor = $bindingDescriptor;
        $this->rootPackageFile = $rootPackageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $uuid = $this->bindingDescriptor->getUuid();

        if ($this->rootPackageFile->hasBindingDescriptor($uuid)) {
            $this->previousDescriptor = $this->rootPackageFile->getBindingDescriptor($uuid);
        }

        $this->rootPackageFile->addBindingDescriptor($this->bindingDescriptor);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootPackageFile->addBindingDescriptor($this->previousDescriptor);
        } else {
            $this->rootPackageFile->removeBindingDescriptor($this->bindingDescriptor->getUuid());
        }
    }
}
