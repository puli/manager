<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installer\Validation;

use Webmozart\Assert\Assert;

/**
 * A violation detected during parameter validation.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConstraintViolation
{
    /**
     * Code: An non-existing parameter was supplied.
     */
    const NO_SUCH_PARAMETER = 1;

    /**
     * Code: A required parameter was missing.
     */
    const MISSING_PARAMETER = 2;

    /**
     * @var int[]
     */
    private static $codes = array(
        self::NO_SUCH_PARAMETER,
        self::MISSING_PARAMETER,
    );

    /**
     * @var int
     */
    private $code;

    /**
     * @var mixed
     */
    private $invalidValue;

    /**
     * @var string
     */
    private $installerName;

    /**
     * @var string|null
     */
    private $parameterName;

    /**
     * Creates the violation.
     *
     * @param int         $code          The violation code. One of the constants
     *                                   defined in this class.
     * @param mixed       $invalidValue  The value that caused this violation.
     * @param string      $installerName The name of the validated installer.
     * @param string|null $parameterName The name of the validated installer
     *                                   parameter or `null` if this is a generic
     *                                   error.
     */
    public function __construct($code, $invalidValue, $installerName, $parameterName = null)
    {
        Assert::oneOf($code, self::$codes, 'The violation code %s is not valid.');
        Assert::stringNotEmpty($installerName, 'The installer name must be a non-empty string. Got: %s');
        Assert::nullOrStringNotEmpty($parameterName, 'The parameter name must be a non-empty string or null. Got: %s');

        $this->code = $code;
        $this->installerName = $installerName;
        $this->parameterName = $parameterName;
        $this->invalidValue = $invalidValue;
    }

    /**
     * Returns the violation code.
     *
     * @return int One of the constants of this class.
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Returns the value that failed validation.
     *
     * @return mixed The value that failed validation.
     */
    public function getInvalidValue()
    {
        return $this->invalidValue;
    }

    /**
     * Returns the name of the validated installer.
     *
     * @return string The validated installer name.
     */
    public function getInstallerName()
    {
        return $this->installerName;
    }

    /**
     * Returns the name of the validated parameter.
     *
     * @return string|null The name of the validated parameter or `null` if
     *                     this is a generic violation.
     */
    public function getParameterName()
    {
        return $this->parameterName;
    }
}
