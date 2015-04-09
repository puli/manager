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
 * Thrown when a binding type was not found.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchTypeException extends RuntimeException
{
    /**
     * Creates an exception for a type name.
     *
     * @param string    $typeName The name of the type.
     * @param Exception $cause    The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forTypeName($typeName, Exception $cause = null)
    {
        return new static(sprintf(
            'The type "%s" does not exist.',
            $typeName
        ), 0, $cause);
    }
}
