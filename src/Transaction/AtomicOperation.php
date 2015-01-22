<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Transaction;

/**
 * An atomic operation.
 *
 * Atomic operations must not cause any side effects if their execution fails.
 * Additionally, atomic operations support the method {@link rollback()} which
 * undoes any side effects caused by {@link execute()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface AtomicOperation
{
    /**
     * Executes the operation.
     *
     * If the execution fails, this method must not cause any side effects in
     * the system.
     */
    public function execute();

    /**
     * Undoes the side effects of the operation.
     *
     * This method is called if the operation needs to be reverted. The method
     * is only called if {@link execute()} completed successfully.
     */
    public function rollback();
}
