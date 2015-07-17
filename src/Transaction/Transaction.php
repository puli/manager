<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Transaction;

/**
 * A sequence of atomic operations that can be rolled back.
 *
 * A transaction is a sequence of atomic operations. The term "atomic" means
 * that the operation either executes successfully or fails without side
 * effects. All atomic operations must implement {@link AtomicOperation}.
 *
 * If an error occurs during the execution of a transaction, all completed
 * operations can be undone by calling {@link rollback()}. If no error
 * occurred, the transaction can be finalized by calling {@link commit()}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Transaction
{
    /**
     * @var AtomicOperation[]
     */
    private $completedOperations = array();

    /**
     * Executes an operation in the scope of this transaction.
     *
     * @param AtomicOperation $operation The operation to execute.
     */
    public function execute(AtomicOperation $operation)
    {
        $operation->execute();

        $this->completedOperations[] = $operation;
    }

    /**
     * Rolls back all executed operations.
     *
     * Only operations that executed successfully are rolled back.
     */
    public function rollback()
    {
        for ($i = count($this->completedOperations) - 1; $i >= 0; --$i) {
            $this->completedOperations[$i]->rollback();
        }

        $this->completedOperations = array();
    }

    /**
     * Commits the transaction.
     */
    public function commit()
    {
        $this->completedOperations = array();
    }
}
