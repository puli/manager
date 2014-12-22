<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Binding;

use Assert\Assertion;
use InvalidArgumentException;
use Puli\Discovery\Binding\BindingType;
use Puli\Discovery\Binding\NoSuchParameterException;

/**
 * Describes a binding type.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    BindingType
 */
class BindingTypeDescriptor
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
     * @var BindingParameterDescriptor[]
     */
    private $parameters = array();

    /**
     * Creates a binding type descriptor.
     *
     * @param string                       $name        The name of the type.
     * @param string|null                  $description A human-readable
     *                                                  description of the type.
     * @param BindingParameterDescriptor[] $parameters  The parameters.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     *
     * @see BindingType
     */
    public function __construct($name, $description = null, array $parameters = array())
    {
        Assertion::string($name, 'The type name must be a string. Got: %2$s');
        Assertion::notEmpty($name, 'The type name must not be empty.');
        Assertion::contains($name, '/', 'The type name must contain a vendor name followed by a "/". Got: "%s"');
        Assertion::nullOrString($description, 'The type description must be a string or null. Got: %2$s');
        Assertion::nullOrNotEmpty($description, 'The type description must not be empty.');
        Assertion::allIsInstanceOf($parameters, __NAMESPACE__.'\BindingParameterDescriptor');

        $this->name = $name;
        $this->description = $description;

        foreach ($parameters as $parameter) {
            $this->parameters[$parameter->getName()] = $parameter;
        }
    }

    /**
     * Returns the type name.
     *
     * @return string The type name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns a human-readable description of the type.
     *
     * @return string|null The description or `null` if none was set.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the descriptors for the parameters of the type.
     *
     * @return BindingParameterDescriptor[] The parameter descriptors.
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Returns the descriptor for a parameter.
     *
     * @param string $name The parameter name.
     *
     * @return BindingParameterDescriptor The descriptor of the parameter.
     *
     * @throws NoSuchParameterException If the parameter was not set.
     */
    public function getParameter($name)
    {
        if (!isset($this->parameters[$name])) {
            throw new NoSuchParameterException(sprintf(
                'The parameter "%s" does not exist.',
                $name
            ));
        }

        return $this->parameters[$name];
    }

    /**
     * Returns whether the descriptor contains a parameter.
     *
     * @param string $name The parameter name.
     *
     * @return bool Whether the type contains a parameter with that name.
     */
    public function hasParameter($name)
    {
        return isset($this->parameters[$name]);
    }

    /**
     * Converts the descriptor to a binding type.
     *
     * @return BindingType The created binding type.
     */
    public function toBindingType()
    {
        $parameters = array();

        foreach ($this->parameters as $parameter) {
            $parameters[] = $parameter->toBindingParameter();
        }

        return new BindingType($this->name, $parameters);
    }
}
