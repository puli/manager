<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery\Type;

use Puli\RepositoryManager\Transaction\OperationInterceptor;
use Rhumsaa\Uuid\Uuid;

/**
 * Updates the duplicate marks of all types with the given type name.
 *
 * If more than one type is defined for the given type name, all types are
 * marked as duplicates. Otherwise the single type is marked as no duplicate.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UpdateDuplicateMarksForTypeName implements OperationInterceptor
{
    /**
     * @var Uuid
     */
    private $typeName;

    /**
     * @var BindingTypeDescriptorStore
     */
    private $typeStore;

    public function __construct($typeName, BindingTypeDescriptorStore $bindingStore)
    {
        $this->typeName = $typeName;
        $this->typeStore = $bindingStore;
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
        if (!$this->typeStore->existsAny($typeName)) {
            return;
        }

        $types = $this->typeStore->getAll($typeName);
        $duplicate = count($types) > 1;

        foreach ($types as $type) {
            $type->markDuplicate($duplicate);
        }
    }
}
