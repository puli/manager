<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Repository;

use Exception;
use RuntimeException;

/**
 * Thrown when a resource mapping was not found.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchMappingException extends RuntimeException
{
    /**
     * Creates an exception for a repository path.
     *
     * @param string    $path  The repository path.
     * @param int       $code  The exception code.
     * @param Exception $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forRepositoryPath($path, $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            'The repository path "%s" was not found.',
            $path
        ), $code, $cause);
    }
}
