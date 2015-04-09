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
 * Thrown when an operation was performed for a non-root package when the root
 * package was expected.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RootPackageExpectedException extends RuntimeException
{
    /**
     * Creates an exception for a binding type that could not be removed from
     * a non-root package.
     *
     * @param string    $typeName    The name of the binding type.
     * @param string    $packageName The name of the package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function cannotRemoveBindingType($typeName, $packageName, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot remove binding type "%s" from package "%s": Can only '.
            'remove types from the root package.',
            $typeName,
            $packageName
        ), 0, $cause);
    }

    /**
     * Creates an exception for a binding UUID that could not be removed from
     * a non-root package.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function cannotRemoveBinding(Uuid $uuid, $packageName, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot remove binding "%s" from package "%s": Can only remove '.
            'bindings from the root package.',
            $uuid->toString(),
            $packageName
        ), 0, $cause);
    }
}
