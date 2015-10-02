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
 * Thrown when the version of a package file is not supported.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnsupportedVersionException extends RuntimeException
{
    /**
     * Creates an exception for an unknown version.
     *
     * @param string    $version       The version that caused the exception.
     * @param string[]  $knownVersions The known versions.
     * @param string    $path          The path of the read package file.
     * @param Exception $cause         The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forVersion($version, array $knownVersions, $path = null, Exception $cause = null)
    {
        usort($knownVersions, 'version_compare');

        $isHigher = version_compare($version, end($knownVersions), '>');

        return new static(sprintf(
            'Cannot read package file%s at version %s. The supported versions '.
            'are %s.%s',
            $path ? ' '.$path : '',
            $version,
            implode(', ', $knownVersions),
            $isHigher ? ' Please run "puli self-update".' : ''
        ), 0, $cause);
    }
}
