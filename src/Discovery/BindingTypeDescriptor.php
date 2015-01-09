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
use Puli\Discovery\Api\Binding\BindingType;
use Puli\Discovery\Api\Binding\NoSuchParameterException;
use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Discovery\Store\BindingTypeStore;

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
     * @var int
     */
    private $state;

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
        Assert::typeName($name);
        Assert::nullOrString($description, 'The type description must be a string or null. Got: %2$s');
        Assert::nullOrNotEmpty($description, 'The type description must not be empty.');
        Assert::allIsInstanceOf($parameters, __NAMESPACE__.'\BindingParameterDescriptor');

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
     * @param string $parameterName The parameter name.
     *
     * @return BindingParameterDescriptor The descriptor of the parameter.
     *
     * @throws NoSuchParameterException If the parameter was not set.
     */
    public function getParameter($parameterName)
    {
        if (!isset($this->parameters[$parameterName])) {
            throw new NoSuchParameterException(sprintf(
                'The parameter "%s" does not exist.',
                $parameterName
            ));
        }

        return $this->parameters[$parameterName];
    }

    /**
     * Returns whether the type has a parameter.
     *
     * @param string $parameterName The parameter name.
     *
     * @return bool Whether the type contains a parameter with that name.
     */
    public function hasParameter($parameterName)
    {
        return isset($this->parameters[$parameterName]);
    }

    /**
     * Returns whether the type has any parameters.
     *
     * @return bool Returns `true` if the type has parameters.
     */
    public function hasParameters()
    {
        return count($this->parameters) > 0;
    }

    /**
     * Returns whether the type has any required parameters.
     *
     * @return bool Returns `true` if the type has at least one required
     *              parameter.
     */
    public function hasRequiredParameters()
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->isRequired()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the type has any optional parameters.
     *
     * @return bool Returns `true` if the type has at least one optional
     *              parameter.
     */
    public function hasOptionalParameters()
    {
        foreach ($this->parameters as $parameter) {
            if (!$parameter->isRequired()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the default values of all optional parameters.
     *
     * @return array The default values.
     */
    public function getParameterValues()
    {
        $values = array();

        foreach ($this->parameters as $name => $parameter) {
            if (!$parameter->isRequired()) {
                $values[$name] = $parameter->getDefaultValue();
            }
        }

        return $values;
    }

    /**
     * Returns the default value of a parameter.
     *
     * @param string $parameterName The parameter name.
     *
     * @return mixed The default value of the parameter.
     *
     * @throws NoSuchParameterException If the parameter was not set.
     */
    public function getParameterValue($parameterName)
    {
        return $this->getParameter($parameterName)->getDefaultValue();
    }

    /**
     * Returns whether the type has any parameters with default values.
     *
     * This method is an alias for {@link hasOptionalParameters()}.
     *
     * @return bool Whether a parameter with a default value exists.
     */
    public function hasParameterValues()
    {
        return $this->hasOptionalParameters();
    }

    /**
     * Returns whether the type has a parameter with a default value.
     *
     * This method checks whether the parameter exists and is optional.
     *
     * @param string $parameterName The parameter name.
     *
     * @return bool Returns `true` if the parameter exists and is optional
     *              (not required).
     */
    public function hasParameterValue($parameterName)
    {
        return $this->hasParameter($parameterName) && !$this->getParameter($parameterName)->isRequired();
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

    /**
     * Returns the state of the binding type.
     *
     * @return int One of the {@link BindingTypeState} constants.
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Resets the state of the binding type to unloaded.
     */
    public function resetState()
    {
        $this->state = BindingTypeState::NOT_LOADED;
    }

    /**
     * Refreshes the state of the binding type.
     *
     * @param BindingTypeStore $typeStore The store with the defined types.
     */
    public function refreshState(BindingTypeStore $typeStore)
    {
        $this->state = BindingTypeState::detect($this, $typeStore);
    }

    /**
     * Returns whether the binding type is loaded.
     *
     * @return bool Returns `true` if the state is not
     *              {@link BindingTypeState::NOT_LOADED}.
     *
     * @see BindingTypeState::NOT_LOADED
     */
    public function isLoaded()
    {
        return BindingTypeState::NOT_LOADED !== $this->state;
    }

    /**
     * Returns whether the binding type is enabled.
     *
     * @return bool Returns `true` if the state is {@link BindingTypeState::ENABLED}.
     *
     * @see BindingTypeState::ENABLED
     */
    public function isEnabled()
    {
        return BindingTypeState::ENABLED === $this->state;
    }

    /**
     * Returns whether the binding type is duplicated.
     *
     * @return bool Returns `true` if the state is {@link BindingTypeState::DUPLICATE}.
     *
     * @see BindingTypeState::DUPLICATE
     */
    public function isDuplicate()
    {
        return BindingTypeState::DUPLICATE === $this->state;
    }
}
