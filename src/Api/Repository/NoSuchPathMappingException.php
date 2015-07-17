<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Repository;

use Exception;
use RuntimeException;

/**
 * Thrown when a path mapping was not found.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchPathMappingException extends RuntimeException
{
    /**
     * Creates an exception for a repository path.
     *
     * @param string    $path  The repository path.
     * @param Exception $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forRepositoryPath($path, Exception $cause = null)
    {
        return new static(sprintf(
            'The repository path "%s" is not mapped.',
            $path
        ), 0, $cause);
    }

    /**
     * Creates an exception for a repository path and a package name.
     *
     * @param string    $path        The repository path.
     * @param string    $packageName The name of the containing package.
     * @param Exception $cause       The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forRepositoryPathAndPackage($path, $packageName, Exception $cause = null)
    {
        return new static(sprintf(
            'The repository path "%s" is not mapped in package "%s".',
            $path,
            $packageName
        ), 0, $cause);
    }
}
