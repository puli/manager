<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Assert;

use Assert\Assertion;
use Puli\RepositoryManager\Package\RootPackage;
use Webmozart\PathUtil\Path;

/**
 * Contains domain-specific assertions.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @method static void nullOrPath($value, $message = null, $propertyPath = null)
 * @method static void nullOrSystemPath($value, $message = null, $propertyPath = null)
 * @method static void nullOrAbsoluteSystemPath($value, $message = null, $propertyPath = null)
 * @method static void nullOrPackageName($value, $message = null, $propertyPath = null)
 * @method static void nullOrQuery($value, $message = null, $propertyPath = null)
 * @method static void nullOrLanguage($value, $message = null, $propertyPath = null)
 * @method static void nullOrTypeName($value, $message = null, $propertyPath = null)
 * @method static void nullOrParameterName($value, $message = null, $propertyPath = null)
 * @method static void nullOrParameterValue($value, $message = null, $propertyPath = null)
 * @method static void allPath($value, $object, $message = null, $propertyPath = null)
 * @method static void allSystemPath($value, $object, $message = null, $propertyPath = null)
 * @method static void allAbsoluteSystemPath($value, $object, $message = null, $propertyPath = null)
 * @method static void allPackageName($value, $object, $message = null, $propertyPath = null)
 * @method static void allQuery($value, $message = null, $propertyPath = null)
 * @method static void allLanguage($value, $message = null, $propertyPath = null)
 * @method static void allTypeName($value, $message = null, $propertyPath = null)
 * @method static void allParameterName($value, $message = null, $propertyPath = null)
 * @method static void allParameterValue($value, $message = null, $propertyPath = null)
 */
class Assert extends Assertion
{
    public static function path($value)
    {
        \Puli\Repository\Assert\Assert::path($value);
    }

    public static function systemPath($value)
    {
        Assert::string($value, 'The path must be a string. Got: %2$s');
        Assert::notEmpty($value, 'The path must not be empty.');
    }

    public static function absoluteSystemPath($value)
    {
        Assert::systemPath($value);
        Assert::true(Path::isAbsolute($value), sprintf(
            'The path "%s" is not absolute.',
            $value
        ));
    }

    public static function packageName($value)
    {
        self::string($value, 'The package name must be a string. Got: %2$s');
        self::notEmpty($value, 'The package name must not be empty.');

        if (RootPackage::DEFAULT_NAME !== $value) {
            self::contains($value, '/', 'The package name must contain a vendor name followed by a "/". Got: "%s"');
        }
    }

    public static function query($value)
    {
        self::string($value, 'The query must be a string. Got: %2$s');
        self::notEmpty($value, 'The query must not be empty.');
    }

    public static function language($value)
    {
        self::string($value, 'The language must be a string. Got: %2$s');
        self::notEmpty($value, 'The language must not be empty.');
    }

    public static function typeName($value)
    {
        Assert::string($value, 'The type name must be a string. Got: %2$s');
        Assert::notEmpty($value, 'The type name must not be empty.');
        Assert::contains($value, '/', 'The type name must contain a vendor name followed by a "/". Got: "%s"');
        Assert::regex($value, '~^[a-z][a-z0-9\-]*/[a-z0-9\-]+$~', 'The type name must contain lower-case characters, digits and hyphens only. Got: "%s"');
    }

    public static function parameterName($value)
    {
        Assert::string($value, 'The parameter name must be a string. Got: %2$s');
        Assert::notEmpty($value, 'The parameter name must not be empty.');
        Assert::regex($value, '~^[a-z][a-z0-9\-]*$~', 'The parameter name must contain lower-case characters, digits and hyphens only and start with a letter. Got: "%s"');
    }

    public static function parameterValue($value)
    {
        Assert::scalar($value, sprintf(
            'The parameter value must be a scalar value. Got: %s',
            is_object($value) ? get_class($value) : gettype($value)
        ));
    }
}
