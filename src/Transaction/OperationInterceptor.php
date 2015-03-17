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
 * An interceptor for an atomic operation.
 *
 * See {@link InterceptedOperation} for a description of interceptors.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    InterceptedOperation
 */
interface OperationInterceptor
{
    /**
     * Invoked after executing the operation.
     *
     * If this method fails, the operation is rolled back.
     * {@link postRollback()} will be called after the rollback and can be used
     * for cleaning up side effects.
     */
    public function postExecute();

    /**
     * Invoked after rolling back the operation.
     *
     * Use this method to revert the side effects of {@link postExecute()} in
     * case the operation needs to be rolled back.
     */
    public function postRollback();
}
