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
 * Thrown when a binding cannot be enabled.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CannotEnableBindingException extends RuntimeException
{
    /**
     * Code: Bindings in the root package cannot be enabled.
     */
    const ROOT_PACKAGE_NOT_ACCEPTED = 1;

    /**
     * Code: Bindings with unloaded types cannot be enabled.
     */
    const TYPE_NOT_LOADED = 2;

    /**
     * Creates an exception for a UUID that could not be enabled because the
     * binding is in the root package.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function rootPackageNotAccepted(Uuid $uuid, $packageName, Exception $cause = null)
    {
        return static::forUuid($uuid, $packageName, '', self::ROOT_PACKAGE_NOT_ACCEPTED, $cause);
    }

    /**
     * Creates an exception for a UUID that could not be enabled because the
     * binding type is not loaded.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function typeNotLoaded(Uuid $uuid, $packageName, Exception $cause = null)
    {
        return static::forUuid($uuid, $packageName, 'The type of the binding is not loaded.', self::TYPE_NOT_LOADED, $cause);
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
            'The binding with UUID "%s" in package "%s" cannot be enabled%s',
            $uuid->toString(),
            $packageName,
            $reason ? ': '.$reason : '.'
        ), $code, $cause);
    }
}
