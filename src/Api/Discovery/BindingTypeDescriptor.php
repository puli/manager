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

use OutOfBoundsException;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Api\Type\NoSuchParameterException;
use Puli\Manager\Api\AlreadyLoadedException;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\NotLoadedException;
use Puli\Manager\Assert\Assert;

/**
 * Describes a binding type.
 *
 * This class contains a high-level model of {@link BindingType} as it is used
 * in this module.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see    BindingType
 */
class BindingTypeDescriptor
{
    /**
     * @var BindingType
     */
    private $type;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string[]
     */
    private $parameterDescriptions = array();

    /**
     * @var bool
     */
    private $bindingOrderStrict = false;

    /**
     * @var int
     */
    private $state;

    /**
     * @var Module
     */
    private $containingModule;

    /**
     * Creates a binding type descriptor.
     *
     * @param BindingType $type                  The described type.
     * @param string|null $description           A human-readable description of
     *                                           the type.
     * @param string[]    $parameterDescriptions Human-readable descriptions
     *                                           indexed by the type's parameter
     *                                           names.
     * @param bool        $bindingOrderStrict    Whether modules containing the
     *                                           bindings of this type must be
     *                                           strictly ordered with "depend"
     *                                           statements.
     *
     * @throws NoSuchParameterException If a description is passed for an unset
     *                                  parameter.
     */
    public function __construct(BindingType $type, $description = null, array $parameterDescriptions = array(), $bindingOrderStrict = false)
    {
        Assert::nullOrStringNotEmpty($description, 'The description must be a non-empty string or null. Got: %s');
        Assert::allStringNotEmpty($parameterDescriptions, 'The parameter description must be a non-empty string. Got: %s');

        $this->type = $type;
        $this->description = $description;
        $this->bindingOrderStrict = (bool) $bindingOrderStrict;

        foreach ($parameterDescriptions as $parameterName => $parameterDescription) {
            if (!$type->hasParameter($parameterName)) {
                throw NoSuchParameterException::forParameterName($parameterName, $type->getName());
            }

            $this->parameterDescriptions[$parameterName] = $parameterDescription;
        }
    }

    /**
     * Loads the type descriptor.
     *
     * @param Module $containingModule The module that contains the type
     *                                 descriptor.
     *
     * @throws AlreadyLoadedException If the descriptor is already loaded.
     */
    public function load(Module $containingModule)
    {
        if (null !== $this->state) {
            throw new AlreadyLoadedException('The type descriptor is already loaded.');
        }

        $this->containingModule = $containingModule;
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

        $this->containingModule = null;
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
     * Returns the name of the described type.
     *
     * @return string The type name.
     */
    public function getTypeName()
    {
        return $this->type->getName();
    }

    /**
     * Returns the described type.
     *
     * @return BindingType The described binding type.
     */
    public function getType()
    {
        return $this->type;
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
     * Returns the descriptions for the parameters of the type.
     *
     * @return string[] The parameter descriptions.
     */
    public function getParameterDescriptions()
    {
        return $this->parameterDescriptions;
    }

    /**
     * Returns the description for a parameter.
     *
     * @param string $parameterName The parameter name.
     *
     * @return string The description of the parameter.
     *
     * @throws NoSuchParameterException If the parameter does not exist.
     * @throws OutOfBoundsException     If the parameter has no description.
     */
    public function getParameterDescription($parameterName)
    {
        if (!$this->type->hasParameter($parameterName)) {
            throw new NoSuchParameterException(sprintf(
                'The parameter "%s" does not exist.',
                $parameterName
            ));
        }

        if (!isset($this->parameterDescriptions[$parameterName])) {
            throw new OutOfBoundsException(sprintf(
                'No description exists for parameter "%s".',
                $parameterName
            ));
        }

        return $this->parameterDescriptions[$parameterName];
    }

    /**
     * Returns whether the type has a description for a parameter.
     *
     * @param string $parameterName The parameter name.
     *
     * @return bool Whether the type contains a parameter with that name.
     */
    public function hasParameterDescription($parameterName)
    {
        return isset($this->parameterDescriptions[$parameterName]);
    }

    /**
     * Returns whether the type has any parameters.
     *
     * @return bool Returns `true` if the type has parameters.
     */
    public function hasParameterDescriptions()
    {
        return count($this->parameterDescriptions) > 0;
    }

    /**
     * Returns whether the modules containing the bindings of this type must
     * be strictly ordered.
     *
     * @return boolean Returns `true` if the modules must be strictly ordered
     *                 and `false` otherwise.
     */
    public function isBindingOrderStrict()
    {
        return $this->bindingOrderStrict;
    }

    /**
     * Returns the module that contains the descriptor.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return Module The containing module.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getContainingModule()
    {
        if (null === $this->containingModule) {
            throw new NotLoadedException('The type descriptor is not loaded.');
        }

        return $this->containingModule;
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
}
