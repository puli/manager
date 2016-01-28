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
     * @param string         $path  The repository path.
     * @param Exception|null $cause The exception that caused this exception.
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
     * Creates an exception for a repository path and a module name.
     *
     * @param string         $path       The repository path.
     * @param string         $moduleName The name of the containing module.
     * @param Exception|null $cause      The exception that caused this
     *                                   exception.
     *
     * @return static The created exception.
     */
    public static function forRepositoryPathAndModule($path, $moduleName, Exception $cause = null)
    {
        return new static(sprintf(
            'The repository path "%s" is not mapped in module "%s".',
            $path,
            $moduleName
        ), 0, $cause);
    }
}
