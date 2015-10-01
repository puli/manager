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

use Puli\Manager\Discovery\Type\BindingTypeDescriptorCollection;

/**
 * Reloads all binding descriptors with a given type name.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReloadBindingDescriptorsByTypeName extends AbstractReloadBindingDescriptors
{
    /**
     * @var string
     */
    private $typeName;

    /**
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    /**
     * @param string                          $typeName
     * @param BindingDescriptorCollection     $bindingDescriptors
     * @param BindingTypeDescriptorCollection $typeDescriptors
     */
    public function __construct($typeName, BindingDescriptorCollection $bindingDescriptors, BindingTypeDescriptorCollection $typeDescriptors)
    {
        parent::__construct($typeDescriptors);

        $this->typeName = $typeName;
        $this->bindingDescriptors = $bindingDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute()
    {
        foreach ($this->bindingDescriptors->toArray() as $bindingDescriptor) {
            if ($this->typeName === $bindingDescriptor->getTypeName()) {
                $this->reloadBindingDescriptor($bindingDescriptor);
            }
        }
    }
}
