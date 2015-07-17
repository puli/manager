<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Discovery;

use InvalidArgumentException;
use Puli\Discovery\Api\Binding\BindingParameter;
use Puli\Manager\Assert\Assert;
use RuntimeException;

/**
 * Describes a binding parameter.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see    BindingParameter
 */
class BindingParameterDescriptor
{
    /**
     * Flag: The parameter is optional.
     */
    const OPTIONAL = 0;

    /**
     * Flag: The parameter is required.
     */
    const REQUIRED = 1;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $flags;

    /**
     * @var mixed
     */
    private $defaultValue;

    /**
     * @var string|null
     */
    private $description;

    /**
     * Creates a binding parameter descriptor.
     *
     * @param string      $name         The parameter name.
     * @param int         $flags        A bitwise combination of the flag
     *                                  constants in this class.
     * @param mixed       $defaultValue The default value to use if the
     *                                  parameter is not set.
     * @param string|null $description  A human-readable description of the
     *                                  parameter.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     * @throws RuntimeException         If a default value is set even though required
     *                                  is set to `true`.
     *
     * @see BindingParameter
     */
    public function __construct($name, $flags = self::OPTIONAL, $defaultValue = null, $description = null)
    {
        Assert::parameterName($name);
        Assert::nullOrInteger($flags, 'The parameter "$flags" must be an integer or null. Got: %s');
        Assert::nullOrParameterValue($defaultValue);
        Assert::nullOrString($description, 'The parameter description must be a string or null. Got: %s');
        Assert::nullOrNotEmpty($description, 'The parameter description must not be empty.');

        if (($flags & self::REQUIRED) && null !== $defaultValue) {
            throw new RuntimeException('Required parameters cannot have default values.');
        }

        $this->name = $name;
        $this->flags = $flags;
        $this->defaultValue = $defaultValue;
        $this->description = $description;
    }

    /**
     * Returns the name of the parameter.
     *
     * @return string The parameter name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the flags passed to the constructor.
     *
     * @return int A bitwise combination of the flag constants in this class.
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Returns the default value of the parameter.
     *
     * The default value is used if the parameter is not set.
     *
     * @return mixed The default value.
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * Returns a human-readable description of the parameter.
     *
     * @return string|null The description or `null` if none was set.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns whether the parameter is required.
     *
     * @return bool Returns `true` if the parameter is required and `false`
     *              otherwise.
     */
    public function isRequired()
    {
        return (bool) ($this->flags & self::REQUIRED);
    }

    /**
     * Converts the descriptor into a binding parameter.
     *
     * @return BindingParameter The created binding parameter.
     */
    public function toBindingParameter()
    {
        $flags = $this->isRequired() ? BindingParameter::REQUIRED : BindingParameter::OPTIONAL;

        return new BindingParameter($this->name, $flags, $this->defaultValue);
    }
}
