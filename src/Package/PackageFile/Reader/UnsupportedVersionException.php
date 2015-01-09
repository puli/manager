<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile\Reader;

use Exception;
use RuntimeException;

/**
 * Thrown when the version of a package file is not supported.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnsupportedVersionException extends RuntimeException
{
    /**
     * Creates an exception when the read version was too high.
     *
     * @param string    $version    The version that caused the exception.
     * @param string    $maxVersion The highest readable version.
     * @param string    $path       The path of the read package file.
     * @param int       $code       The exception code.
     * @param Exception $cause      The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function versionTooHigh($version, $maxVersion, $path, $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot read package file %s at version %s. The highest readable '.
            'version is %s. Please upgrade Puli.',
            $path,
            $version,
            $maxVersion
        ), $code, $cause);
    }

    /**
     * Creates an exception when the read version was too low.
     *
     * @param string    $version    The version that caused the exception.
     * @param string    $minVersion The lowest readable version.
     * @param string    $path       The path of the read package file.
     * @param int       $code       The exception code.
     * @param Exception $cause      The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function versionTooLow($version, $minVersion, $path, $code = 0, Exception $cause = null)
    {
        return new static(sprintf(
            'Cannot read package file %s at version %s. The lowest readable '.
            'version is %s. Please upgrade the package file.',
            $path,
            $version,
            $minVersion
        ), $code, $cause);
    }
}
