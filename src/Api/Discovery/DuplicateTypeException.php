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
use RuntimeException;

/**
 * Thrown when a duplicate binding type is detected.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DuplicateTypeException extends RuntimeException
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
            'The type "%s" is already defined.',
            $typeName
        ), 0, $cause);
    }
}
