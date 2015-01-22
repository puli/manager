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

use Puli\RepositoryManager\Discovery\Type\BindingTypeDescriptorStore;
use Rhumsaa\Uuid\Uuid;

/**
 * Reloads all binding descriptors with a given UUID.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReloadBindingDescriptorsByUuid extends AbstractReloadBindingDescriptors
{
    /**
     * @var Uuid
     */
    private $uuid;

    /**
     * @var BindingDescriptorStore
     */
    private $bindingStore;

    public function __construct(Uuid $uuid, BindingDescriptorStore $bindingStore, BindingTypeDescriptorStore $typeStore)
    {
        parent::__construct($typeStore);

        $this->uuid = $uuid;
        $this->bindingStore = $bindingStore;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute()
    {
        foreach ($this->bindingStore->getAll($this->uuid) as $bindingDescriptor) {
            $this->reloadBindingDescriptor($bindingDescriptor);
        }
    }
}
