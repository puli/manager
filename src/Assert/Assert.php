<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Assert;

use Webmozart\PathUtil\Path;

/**
 * Domain-specific assertions.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @method static void nullOrSystemPath($value, $message = null, $propertyPath = null)
 * @method static void nullOrAbsoluteSystemPath($value, $message = null, $propertyPath = null)
 * @method static void nullOrModuleName($value, $message = null, $propertyPath = null)
 * @method static void nullOrQuery($value, $message = null, $propertyPath = null)
 * @method static void nullOrLanguage($value, $message = null, $propertyPath = null)
 * @method static void nullOrTypeName($value, $message = null, $propertyPath = null)
 * @method static void nullOrParameterName($value, $message = null, $propertyPath = null)
 * @method static void nullOrParameterValue($value, $message = null, $propertyPath = null)
 * @method static void allSystemPath($value, $object, $message = null, $propertyPath = null)
 * @method static void allAbsoluteSystemPath($value, $object, $message = null, $propertyPath = null)
 * @method static void allModuleName($value, $object, $message = null, $propertyPath = null)
 * @method static void allQuery($value, $message = null, $propertyPath = null)
 * @method static void allLanguage($value, $message = null, $propertyPath = null)
 * @method static void allTypeName($value, $message = null, $propertyPath = null)
 * @method static void allParameterName($value, $message = null, $propertyPath = null)
 * @method static void allParameterValue($value, $message = null, $propertyPath = null)
 */
class Assert extends \Webmozart\Assert\Assert
{
    public static function path($value)
    {
        self::stringNotEmpty($value, 'The path must be a non-empty string. Got: %s');
        self::startsWith($value, '/', 'The path %s is not absolute.');
    }

    public static function systemPath($value)
    {
        self::stringNotEmpty($value, 'The path must be a non-empty string. Got: %s');
    }

    public static function absoluteSystemPath($value)
    {
        self::stringNotEmpty($value, 'The path must be a non-empty string. Got: %s');
        self::true(Path::isAbsolute($value), sprintf(
            'The path %s is not absolute.',
            $value
        ));
    }

    public static function moduleName($value)
    {
        self::stringNotEmpty($value, 'The module name must be a non-empty string. Got: %s');

        if ('__root__' !== $value) {
            self::contains($value, '/', 'The module name %s must contain a vendor name followed by a "/".');
        }
    }

    public static function query($value)
    {
        self::stringNotEmpty($value, 'The query must be a non-empty string. Got: %s');
    }

    public static function language($value)
    {
        self::stringNotEmpty($value, 'The language must be a non-empty string. Got: %s');
    }

    public static function typeName($value)
    {
        self::stringNotEmpty($value, 'The type name must be a non-empty string. Got: %s');
        self::contains($value, '/', 'The type name %s must contain a vendor name followed by a "/".');
        self::startsWithLetter($value, 'The type name %s must start with a letter.');
        self::regex($value, '~^[a-z][a-z0-9\-]*/[a-z0-9\-]+$~', 'The type name %s must contain lower-case characters, digits and hyphens only.');
    }

    public static function parameterName($value)
    {
        self::stringNotEmpty($value, 'The parameter name must be a non-empty string. Got: %s');
        self::startsWithLetter($value, 'The parameter name %s must start with a letter.');
        self::regex($value, '~^[a-z][a-z0-9\-]*$~', 'The parameter %s name must contain lower-case characters, digits and hyphens only.');
    }

    public static function parameterValue($value)
    {
        self::scalar($value, 'The parameter value must be a scalar. Got: %s');
    }
}
