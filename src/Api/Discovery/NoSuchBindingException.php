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
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchBindingException extends RuntimeException
{
    /**
     * Creates an exception for a UUID that was not found.
     *
     * @param Uuid           $uuid  The UUID.
     * @param Exception|null $cause The exception that caused this exception.
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
     * Creates an exception for a UUID that was not found in a given module.
     *
     * @param Uuid           $uuid       The UUID.
     * @param string         $moduleName The name of the containing module.
     * @param Exception|null $cause      The exception that caused this
     *                                   exception.
     *
     * @return static The created exception.
     */
    public static function forUuidAndModule(Uuid $uuid, $moduleName, Exception $cause = null)
    {
        return new static(sprintf(
            'The binding with UUID "%s" does not exist in module "%s".',
            $uuid->toString(),
            $moduleName
        ), 0, $cause);
    }
}
