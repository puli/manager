<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Discovery;

use Puli\Discovery\Api\Binding\BindingType;
use Puli\Discovery\Api\Binding\NoSuchParameterException;
use Puli\RepositoryManager\Api\AlreadyLoadedException;
use Puli\RepositoryManager\Api\NotLoadedException;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Assert\Assert;

/**
 * Describes a binding type.
 *
 * This class contains a high-level model of {@link BindingType} as it is used
 * in this package.
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
     * @var Package
     */
    private $containingPackage;

    /**
     * Creates a binding type descriptor.
     *
     * @param string                       $name        The name of the type.
     * @param string|null                  $description A human-readable
     *                                                  description of the type.
     * @param BindingParameterDescriptor[] $parameters  The parameters.
     *
     * @see BindingType
     */
    public function __construct($name, $description = null, array $parameters = array())
    {
        Assert::typeName($name);
        Assert::nullOrString($description, 'The description must be a string or null. Got: %s');
        Assert::nullOrNotEmpty($description, 'The description must not be empty.');
        Assert::allIsInstanceOf($parameters, __NAMESPACE__.'\BindingParameterDescriptor');

        $this->name = $name;
        $this->description = $description;

        foreach ($parameters as $parameter) {
            $this->parameters[$parameter->getName()] = $parameter;
        }
    }

    /**
     * Loads the type descriptor.
     *
     * @param Package $containingPackage The package that contains the type
     *                                   descriptor.
     *
     * @throws AlreadyLoadedException If the descriptor is already loaded.
     */
    public function load(Package $containingPackage)
    {
        if (null !== $this->state) {
            throw new AlreadyLoadedException('The type descriptor is already loaded.');
        }

        $this->containingPackage = $containingPackage;
        $this->state = BindingTypeState::ENABLED;
    }

    /**
     * Unloads the type descriptor.
     *
     * All memory allocated during {@link load()} is freed.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function unload()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The type descriptor is not loaded.');
        }

        $this->containingPackage = null;
        $this->state = null;
    }

    /**
     * Returns whether the descriptor is loaded.
     *
     * @return bool Returns `true` if the descriptor is loaded.
     */
    public function isLoaded()
    {
        return null !== $this->state;
    }

    /**
     * Marks or unmarks the type as duplicate.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @param bool $duplicate Whether or not the type is a duplicate.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function markDuplicate($duplicate)
    {
        Assert::boolean($duplicate);

        if (null === $this->state) {
            throw new NotLoadedException('The type descriptor is not loaded.');
        }

        $this->state = $duplicate ? BindingTypeState::DUPLICATE : BindingTypeState::ENABLED;
    }

    /**
     * Returns the type's name.
     *
     * @return string The name.
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
     * Returns the package that contains the descriptor.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return Package The containing package.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getContainingPackage()
    {
        if (null === $this->containingPackage) {
            throw new NotLoadedException('The type descriptor is not loaded.');
        }

        return $this->containingPackage;
    }

    /**
     * Returns the state of the binding type.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return int One of the {@link BindingTypeState} constants.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getState()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The type descriptor is not loaded.');
        }

        return $this->state;
    }

    /**
     * Returns whether the binding type is enabled.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingTypeState::ENABLED}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingTypeState::ENABLED
     */
    public function isEnabled()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The type descriptor is not loaded.');
        }

        return BindingTypeState::ENABLED === $this->state;
    }

    /**
     * Returns whether the binding type is duplicated.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingTypeState::DUPLICATE}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingTypeState::DUPLICATE
     */
    public function isDuplicate()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The type descriptor is not loaded.');
        }

        return BindingTypeState::DUPLICATE === $this->state;
    }

    /**
     * Returns whether the binding type matches the given criteria.
     *
     * @param BindingTypeCriteria $criteria The search criteria.
     *
     * @return bool Returns `true` if the binding type matches the criteria and
     *              `false` otherwise.
     *
     * @see BindingTypeCriteria
     */
    public function match(BindingTypeCriteria $criteria)
    {
        return $criteria->matchPackageName($this->containingPackage->getName())
            && $criteria->matchState($this->state);
    }
}
