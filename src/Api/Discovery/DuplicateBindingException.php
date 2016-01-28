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
 * Thrown when a duplicate binding is detected.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DuplicateBindingException extends RuntimeException
{
    /**
     * Creates an exception for a duplicate UUID.
     *
     * @param Uuid           $uuid  The UUID.
     * @param Exception|null $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forUuid(Uuid $uuid, Exception $cause = null)
    {
        return new static(sprintf(
            'A binding with UUID "%s" exists already.',
            $uuid->toString()
        ), 0, $cause);
    }
}
