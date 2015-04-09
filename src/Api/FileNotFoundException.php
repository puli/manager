<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api;

use Exception;
use RuntimeException;

/**
 * Thrown when a file was not found.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FileNotFoundException extends RuntimeException
{
    /**
     * Creates an exception for file path.
     *
     * @param string    $path  The path of the file that could not be found.
     * @param Exception $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forPath($path, Exception $cause = null)
    {
        return new static(sprintf(
            'The file %s does not exist.',
            $path
        ), 0, $cause);
    }
}
