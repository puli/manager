<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installation;

use Exception;

/**
 * Thrown when the installation of resources fails.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NotInstallableException extends Exception
{
    /**
     * Code: An installer parameter is missing.
     */
    const MISSING_PARAMETER = 1;

    /**
     * Code: A non-existing installer parameter was passed.
     */
    const NO_SUCH_PARAMETER = 2;

    /**
     * Code: The installer was not found.
     */
    const INSTALLER_NOT_FOUND = 3;

    /**
     * Code: The resource glob did not return any matches.
     */
    const NO_RESOURCE_MATCHES = 4;

    /**
     * Code: The server was not found.
     */
    const SERVER_NOT_FOUND = 5;

    /**
     * Code: The installer class was not found.
     */
    const INSTALLER_CLASS_NOT_FOUND = 6;

    /**
     * Code: The installer class has no default constructor.
     */
    const INSTALLER_CLASS_NO_DEFAULT_CONSTRUCTOR = 7;

    /**
     * Code: The installer class does not implement {@link ResourceInstaller}.
     */
    const INSTALLER_CLASS_INVALID = 8;

    /**
     * Creates an exception for a missing required parameter.
     *
     * @param string    $parameterName The parameter name.
     * @param string    $installerName The installer name.
     * @param Exception $cause         The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function missingParameter($parameterName, $installerName, Exception $cause = null)
    {
        return new static(sprintf(
            'The parameter "%s" is required for the installer "%s".',
            $parameterName,
            $installerName
        ), self::MISSING_PARAMETER, $cause);
    }

    /**
     * Creates an exception for a parameter name that was not found.
     *
     * @param string    $parameterName The parameter name.
     * @param string    $installerName The installer name.
     * @param Exception $cause         The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function noSuchParameter($parameterName, $installerName, Exception $cause = null)
    {
        return new static(sprintf(
            'The parameter "%s" does not exist for the installer "%s".',
            $parameterName,
            $installerName
        ), self::NO_SUCH_PARAMETER, $cause);
    }

    /**
     * Creates an exception for an installer name that was not found.
     *
     * @param string    $installerName The installer name.
     * @param Exception $cause         The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function installerNotFound($installerName, Exception $cause = null)
    {
        return new static(sprintf(
            'The installer "%s" does not exist.',
            $installerName
        ), self::INSTALLER_NOT_FOUND, $cause);
    }

    /**
     * Creates an exception for an glob that did not return any matches.
     *
     * @param string    $glob  The resource glob.
     * @param Exception $cause The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function noResourceMatches($glob, Exception $cause = null)
    {
        return new static(sprintf(
            'The glob "%s" did not return any resources.',
            $glob
        ), self::NO_RESOURCE_MATCHES, $cause);
    }

    /**
     * Creates an exception for a server name that was not found.
     *
     * @param string    $serverName The server name.
     * @param Exception $cause      The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function serverNotFound($serverName, Exception $cause = null)
    {
        return new static(sprintf(
            'The asset server "%s" does not exist.',
            $serverName
        ), self::SERVER_NOT_FOUND, $cause);
    }

    /**
     * Creates an exception for an installer class that was not found.
     *
     * @param string    $installerClass The installer class.
     * @param Exception $cause          The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function installerClassNotFound($installerClass, Exception $cause = null)
    {
        return new static(sprintf(
            'The installer class "%s" does not exist.',
            $installerClass
        ), self::INSTALLER_CLASS_NOT_FOUND, $cause);
    }

    /**
     * Creates an exception for an installer class that has no default
     * constructor.
     *
     * @param string    $installerClass The installer class.
     * @param Exception $cause          The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function installerClassNoDefaultConstructor($installerClass, Exception $cause = null)
    {
        return new static(sprintf(
            'The constructor of class "%s" must not have required parameters.',
            $installerClass
        ), self::INSTALLER_CLASS_NO_DEFAULT_CONSTRUCTOR, $cause);
    }

    /**
     * Creates an exception for an installer class that does not implement
     * {@link ResourceInstaller}.
     *
     * @param string    $installerClass The installer class.
     * @param Exception $cause          The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function installerClassInvalid($installerClass, Exception $cause = null)
    {
        return new static(sprintf(
            'The installer class "%s" must implement ResourceInstaller.',
            $installerClass
        ), self::INSTALLER_CLASS_INVALID, $cause);
    }
}
