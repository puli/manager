<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Binding;

use Puli\RepositoryManager\Api\Package\InstallInfo;
use Puli\RepositoryManager\Transaction\AtomicOperation;
use Rhumsaa\Uuid\Uuid;

/**
 * Disables a binding descriptor for a given install info.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DisableBindingUuid implements AtomicOperation
{
    /**
     * @var Uuid
     */
    private $uuid;

    /**
     * @var InstallInfo
     */
    private $installInfo;

    /**
     * @var bool
     */
    private $wasEnabled = false;

    public function __construct(Uuid $uuid, InstallInfo $installInfo)
    {
        $this->uuid = $uuid;
        $this->installInfo = $installInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if ($this->installInfo->hasEnabledBindingUuid($this->uuid)) {
            $this->wasEnabled = true;
        }

        $this->installInfo->addDisabledBindingUuid($this->uuid);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->wasEnabled) {
            $this->installInfo->addEnabledBindingUuid($this->uuid);
        } else {
            $this->installInfo->removeDisabledBindingUuid($this->uuid);
        }
    }
}
