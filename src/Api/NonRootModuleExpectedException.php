<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api;

use Exception;
use Rhumsaa\Uuid\Uuid;
use RuntimeException;

/**
 * Thrown when an operation was performed for the root module when a non-root
 * module was expected.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NonRootModuleExpectedException extends RuntimeException
{
    /**
     * Creates an exception for a binding UUID that could not be enabled in the
     * root module.
     *
     * @param Uuid           $uuid       The UUID.
     * @param string         $moduleName The name of the module.
     * @param Exception|null $cause      The exception that caused this
     *                                   exception.
     *
     * @return static The created exception.
     */
    public static function cannotEnableBinding(Uuid $uuid, $moduleName, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot enable binding "%s" in module "%s": Can only enable '.
            'bindings in non-root modules.',
            $uuid->toString(),
            $moduleName
        ), 0, $cause);
    }

    /**
     * Creates an exception for a binding UUID that could not be disabled in the
     * root module.
     *
     * @param Uuid           $uuid       The UUID.
     * @param string         $moduleName The name of the module.
     * @param Exception|null $cause      The exception that caused this
     *                                   exception.
     *
     * @return static The created exception.
     */
    public static function cannotDisableBinding(Uuid $uuid, $moduleName, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot disable binding "%s" in module "%s": Can only disable '.
            'bindings in non-root modules.',
            $uuid->toString(),
            $moduleName
        ), 0, $cause);
    }
}
