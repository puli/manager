<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Discovery;

use Exception;
use Rhumsaa\Uuid\Uuid;
use RuntimeException;

/**
 * Thrown when a binding cannot be removed.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CannotRemoveBindingException extends RuntimeException
{
    /**
     * Code: Bindings can only be removed from the root package.
     */
    const ROOT_PACKAGE_REQUIRED = 1;

    /**
     * Creates an exception for a UUID that could not be removed because the
     * binding is not in the root package.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function rootPackageRequired(Uuid $uuid, $packageName, Exception $cause = null)
    {
        return static::forUuid($uuid, $packageName, 'Bindings can only be removed from the root package.', self::ROOT_PACKAGE_REQUIRED, $cause);
    }

    /**
     * Creates an exception for a UUID that could not be enabled.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the package.
     * @param string    $reason      The reason why the binding could not be enabled.
     * @param int       $code        The exception code.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    private static function forUuid(Uuid $uuid, $packageName, $reason = '', $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            'The binding with UUID "%s" cannot be removed from package "%s"%s',
            $uuid->toString(),
            $packageName,
            $reason ? ': '.$reason : '.'
        ), $code, $cause);
    }
}
