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
 * Thrown when a binding was not found.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchBindingException extends RuntimeException
{
    /**
     * Creates an exception for a UUID that was not found.
     *
     * @param Uuid      $uuid  The UUID.
     * @param Exception $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forUuid(Uuid $uuid, Exception $cause = null)
    {
        return new static(sprintf(
            'The binding with UUID "%s" does not exist.',
            $uuid->toString()
        ), 0, $cause);
    }

    /**
     * Creates an exception for a UUID that was not found in a given package.
     *
     * @param Uuid      $uuid        The UUID.
     * @param string    $packageName The name of the containing package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forUuidAndPackage(Uuid $uuid, $packageName, Exception $cause = null)
    {
        return new static(sprintf(
            'The binding with UUID "%s" does not exist in package "%s".',
            $uuid->toString(),
            $packageName
        ), 0, $cause);
    }
}
