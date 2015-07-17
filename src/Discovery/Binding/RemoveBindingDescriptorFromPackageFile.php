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
use Rhumsaa\Uuid\Uuid;

/**
 * Removes a binding descriptor from the root package file.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RemoveBindingDescriptorFromPackageFile implements AtomicOperation
{
    /**
     * @var Uuid
     */
    private $uuid;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var BindingDescriptor
     */
    private $previousDescriptor;

    public function __construct(Uuid $uuid, RootPackageFile $rootPackageFile)
    {
        $this->uuid = $uuid;
        $this->rootPackageFile = $rootPackageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->rootPackageFile->hasBindingDescriptor($this->uuid)) {
            return;
        }

        $this->previousDescriptor = $this->rootPackageFile->getBindingDescriptor($this->uuid);
        $this->rootPackageFile->removeBindingDescriptor($this->uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->previousDescriptor) {
            $this->rootPackageFile->addBindingDescriptor($this->previousDescriptor);
        }
    }
}
