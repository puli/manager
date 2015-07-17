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

use Puli\Manager\Transaction\OperationInterceptor;
use Rhumsaa\Uuid\Uuid;

/**
 * Updates the duplicate marks of all types with the given type name.
 *
 * If more than one type is defined for the given type name, all types are
 * marked as duplicates. Otherwise the single type is marked as no duplicate.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UpdateDuplicateMarksForTypeName implements OperationInterceptor
{
    /**
     * @var Uuid
     */
    private $typeName;

    /**
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    public function __construct($typeName, BindingTypeDescriptorCollection $typeDescriptors)
    {
        $this->typeName = $typeName;
        $this->typeDescriptors = $typeDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute()
    {
        $this->updateDuplicateMarksForTypeName($this->typeName);
    }

    /**
     * {@inheritdoc}
     */
    public function postRollback()
    {
        $this->updateDuplicateMarksForTypeName($this->typeName);
    }

    private function updateDuplicateMarksForTypeName($typeName)
    {
        if (!$this->typeDescriptors->contains($typeName)) {
            return;
        }

        $typeDescriptors = $this->typeDescriptors->listByTypeName($typeName);
        $duplicate = count($typeDescriptors) > 1;

        foreach ($typeDescriptors as $typeDescriptor) {
            $typeDescriptor->markDuplicate($duplicate);
        }
    }
}
