<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Server;

use Exception;

/**
 * Thrown when a server was not found.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchServerException extends Exception
{
    /**
     * Creates an exception for a server name that was not found.
     *
     * @param string         $serverName The server name.
     * @param Exception|null $cause      The exception that caused this
     *                                   exception.
     *
     * @return static The created exception.
     */
    public static function forServerName($serverName, Exception $cause = null)
    {
        return new static(sprintf(
            'The asset server "%s" does not exist.',
            $serverName
        ), 0, $cause);
    }
}
