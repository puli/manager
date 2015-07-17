<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installer;

use Exception;

/**
 * Thrown when an installer parameter was not found.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NoSuchParameterException extends Exception
{
    /**
     * Creates an exception for a parameter name that was not found.
     *
     * @param string    $parameterName The parameter name.
     * @param string    $installerName The installer name.
     * @param Exception $cause         The exception that caused this exception.
     *
     * @return static The created exception.
     */
    public static function forParameterName($parameterName, $installerName, Exception $cause = null)
    {
        return new static(sprintf(
            'The installer parameter "%s" does not exist for the "%s" installer.',
            $parameterName,
            $installerName
        ), 0, $cause);
    }
}
