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

use Puli\RepositoryManager\Discovery\Type\BindingTypeDescriptorCollection;
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
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    public function __construct(Uuid $uuid, BindingDescriptorCollection $bindingDescriptors, BindingTypeDescriptorCollection $typeDescriptors)
    {
        parent::__construct($typeDescriptors);

        $this->uuid = $uuid;
        $this->bindingDescriptors = $bindingDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute()
    {
        foreach ($this->bindingDescriptors->listByUuid($this->uuid) as $bindingDescriptor) {
            $this->reloadBindingDescriptor($bindingDescriptor);
        }
    }
}
