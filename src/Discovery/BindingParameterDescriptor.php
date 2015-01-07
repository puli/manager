<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Discovery;

use InvalidArgumentException;
use Puli\Discovery\Api\Binding\BindingParameter;
use Puli\RepositoryManager\Assert\Assertion;
use RuntimeException;

/**
 * Describes a binding parameter.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    BindingParameter
 */
class BindingParameterDescriptor
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $required;

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
     * @param bool        $required     Whether the parameter must be set.
     * @param mixed       $defaultValue The default value to use if the
     *                                  parameter is not set.
     * @param string|null $description  A human-readable description of the
     *                                  parameter.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     * @throws RuntimeException If a default value is set even though required
     *                          is set to `true`.
     *
     * @see BindingParameter
     */
    public function __construct($name, $required = false, $defaultValue = null, $description = null)
    {
        Assertion::parameterName($name);
        Assertion::boolean($required, 'The parameter "$required" must be a boolean. Got: %s');
        Assertion::nullOrParameterValue($defaultValue);
        Assertion::nullOrString($description, 'The parameter description must be a string or null. Got: %2$s');
        Assertion::nullOrNotEmpty($description, 'The parameter description must not be empty.');

        if ($required && null !== $defaultValue) {
            throw new RuntimeException('Required parameters cannot have default values.');
        }

        $this->name = $name;
        $this->required = $required;
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
     * Returns whether the parameter is required.
     *
     * @return bool Returns `true` if the parameter is required.
     */
    public function isRequired()
    {
        return $this->required;
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
     * Converts the descriptor into a binding parameter.
     *
     * @return BindingParameter The created binding parameter.
     */
    public function toBindingParameter()
    {
        return new BindingParameter($this->name, $this->required, $this->defaultValue);
    }
}
