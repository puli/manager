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

use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Transaction\AtomicOperation;
use Rhumsaa\Uuid\Uuid;

/**
 * Enables a binding descriptor for a given install info.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class EnableBindingUuid implements AtomicOperation
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
    private $wasDisabled = false;

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
        if ($this->installInfo->hasDisabledBindingUuid($this->uuid)) {
            $this->wasDisabled = true;
            $this->installInfo->removeDisabledBindingUuid($this->uuid);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->wasDisabled) {
            $this->installInfo->addDisabledBindingUuid($this->uuid);
        }
    }
}
