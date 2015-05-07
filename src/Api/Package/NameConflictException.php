<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Exception;
use RuntimeException;

/**
 * Thrown when two packages have the same name.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NameConflictException extends RuntimeException
{
    /**
     * Creates a new exception.
     *
     * @param string    $name  The conflicting name.
     * @param Exception $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forName($name, Exception $cause = null)
    {
        return new static(sprintf(
            'A package with the name "%s" exists already.',
            $name
        ), 0, $cause);
    }
}
