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

use Exception;
use Puli\Manager\Assert\Assert;

/**
 * Adds support for interceptors to an atomic operation.
 *
 * The operation and its interceptors are passed to the constructor. All
 * interceptors must implement {@link OperationInterceptor}.
 *
 * The {@link OperationInterceptor} interface supports two methods:
 *
 *  * {@link OperationInterceptor::postExecute()} is invoked after executing
 *    the operation. If the execution fails, the interceptor is not invoked.
 *    If the interceptor fails, the operation is rolled back.
 *  * {@link OperationInterceptor::postRollback()} is invoked after rolling
 *    back the operation. Consequently, this method is also called when
 *    {@link OperationInterceptor::postExecute()} fails.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InterceptedOperation implements AtomicOperation
{
    /**
     * @var AtomicOperation
     */
    private $operation;

    /**
     * @var OperationInterceptor[]
     */
    private $interceptors;

    /**
     * @var OperationInterceptor[]
     */
    private $interceptorsForRollback;

    /**
     * Adds support for interceptors to an atomic operation.
     *
     * @param AtomicOperation                             $operation    The operation.
     * @param OperationInterceptor|OperationInterceptor[] $interceptors The interceptor(s).
     */
    public function __construct(AtomicOperation $operation, $interceptors)
    {
        $interceptors = is_array($interceptors) ? $interceptors : array($interceptors);

        Assert::allIsInstanceOf($interceptors, __NAMESPACE__.'\OperationInterceptor');

        $this->operation = $operation;
        $this->interceptors = $interceptors;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // no rollback if execute() fails
        $this->operation->execute();

        try {
            // rollback if postExecute() fails, since execute() is already
            // completed
            foreach ($this->interceptors as $interceptor) {
                $this->interceptorsForRollback[] = $interceptor;
                $interceptor->postExecute();
            }
        } catch (Exception $e) {
            $this->rollback();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->operation->rollback();

        // Only launch interceptors whose postExecute() method was called
        foreach ($this->interceptorsForRollback as $interceptor) {
            $interceptor->postRollback();
        }
    }
}
