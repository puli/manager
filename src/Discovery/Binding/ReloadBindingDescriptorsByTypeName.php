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

/**
 * Reloads all binding descriptors with a given type name.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReloadBindingDescriptorsByTypeName extends AbstractReloadBindingDescriptors
{
    /**
     * @var string
     */
    private $typeName;

    /**
     * @var BindingDescriptorStore
     */
    private $bindingStore;

    public function __construct($typeName, BindingDescriptorStore $bindingStore, BindingTypeDescriptorStore $typeStore)
    {
        parent::__construct($typeStore);

        $this->typeName = $typeName;
        $this->bindingStore = $bindingStore;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute()
    {
        foreach ($this->bindingStore->getUuids() as $uuid) {
            foreach ($this->bindingStore->getAll($uuid) as $bindingDescriptor) {
                if ($this->typeName === $bindingDescriptor->getTypeName()) {
                    $this->reloadBindingDescriptor($bindingDescriptor);
                }
            }
        }
    }
}
