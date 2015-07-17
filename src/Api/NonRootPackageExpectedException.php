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
 * Thrown when an operation was performed for the root package when a non-root
 * package was expected.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NonRootPackageExpectedException extends RuntimeException
{
    /**
     * Creates an exception for a binding UUID that could not be enabled in the
     * root package.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function cannotEnableBinding(Uuid $uuid, $packageName, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot enable binding "%s" in package "%s": Can only enable '.
            'bindings in non-root packages.',
            $uuid->toString(),
            $packageName
        ), 0, $cause);
    }

    /**
     * Creates an exception for a binding UUID that could not be disabled in the
     * root package.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function cannotDisableBinding(Uuid $uuid, $packageName, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot disable binding "%s" in package "%s": Can only disable '.
            'bindings in non-root packages.',
            $uuid->toString(),
            $packageName
        ), 0, $cause);
    }
}
