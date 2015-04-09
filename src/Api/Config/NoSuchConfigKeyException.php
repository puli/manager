<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Config;

use Exception;
use RuntimeException;

/**
 * Thrown when a configuration key does not exist.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchConfigKeyException extends RuntimeException
{
    /**
     * Creates an exception for a configuration key.
     *
     * @param string    $key   The configuration key that was not found.
     * @param Exception $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forKey($key, Exception $cause = null)
    {
        return new static(sprintf(
            'The config key "%s" does not exist.',
            $key
        ), 0, $cause);
    }
}
