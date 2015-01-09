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

use BadMethodCallException;
use InvalidArgumentException;
use Puli\Discovery\Api\Binding\NoSuchParameterException;
use Puli\Discovery\Api\Validation\ConstraintViolation;
use Puli\Discovery\Validation\SimpleParameterValidator;
use Puli\RepositoryManager\Assert\Assert;
use Puli\RepositoryManager\Discovery\Store\BindingTypeStore;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Util\DistinguishedName;
use Rhumsaa\Uuid\Uuid;

/**
 * Describes a resource binding.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    ResourceBinding
 */
class BindingDescriptor
{
    /**
     * @var Uuid
     */
    private $uuid;

    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $typeName;

    /**
     * @var array
     */
    private $parameterValues;

    /**
     * @var int
     */
    private $state = BindingState::NOT_LOADED;

    /**
     * @var BindingTypeDescriptor
     */
    private $type;

    /**
     * @var ConstraintViolation[]
     */
    private $violations = array();

    /**
     * Creates a new binding descriptor with a generated UUID.
     *
     * The UUID is generated based on the given parameters.
     *
     * @param string $query           The query for the resources of the binding.
     * @param string $typeName        The name of the binding type.
     * @param array  $parameterValues The values of the binding parameters.
     * @param string $language        The language of the query.
     *
     * @return static The created binding descriptor.
     *
     * @see ResourceBinding
     */
    public static function create($query, $typeName, array $parameterValues = array(), $language = 'glob')
    {
        Assert::query($query);
        Assert::typeName($typeName);
        Assert::language($language);
        Assert::allParameterName(array_keys($parameterValues));
        Assert::allParameterValue($parameterValues);

        $dn = new DistinguishedName(array(
            'q' => $query,
            'l' => $language,
            't' => $typeName,
        ));

        foreach ($parameterValues as $parameterName => $value) {
            // Attribute values must be strings
            $dn->add('p-'.$parameterName, serialize($value));
        }

        $uuid = Uuid::uuid5(Uuid::NAMESPACE_X500, $dn->toString());

        return new static($uuid, $query, $typeName, $parameterValues, $language);
    }

    /**
     * Creates a new binding descriptor.
     *
     * @param Uuid   $uuid            The UUID of the binding.
     * @param string $query           The query for the resources of the binding.
     * @param string $typeName        The name of the binding type.
     * @param array  $parameterValues The values of the binding parameters.
     * @param string $language        The language of the query.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     *
     * @see ResourceBinding
     */
    public function __construct(Uuid $uuid, $query, $typeName, array $parameterValues = array(), $language = 'glob')
    {
        Assert::query($query);
        Assert::typeName($typeName);
        Assert::language($language);
        Assert::allParameterName(array_keys($parameterValues));
        Assert::allParameterValue($parameterValues);

        $this->uuid = $uuid;
        $this->query = $query;
        $this->language = $language;
        $this->typeName = $typeName;
        $this->parameterValues = $parameterValues;
    }

    /**
     * Returns the UUID of the binding.
     *
     * @return Uuid The universally unique ID.
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Returns the query for the resources of the binding.
     *
     * @return string The resource query.
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Returns the language of the query.
     *
     * @return string The query language.
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Returns the name of the binding type.
     *
     * @return string The type name.
     */
    public function getTypeName()
    {
        return $this->typeName;
    }

    /**
     * Returns the values of the binding parameters.
     *
     * @param bool $includeDefault Whether to include the default values set
     *                             in the binding type.
     *
     * @return array The parameter values.
     */
    public function getParameterValues($includeDefault = true)
    {
        $values = $this->parameterValues;

        if ($this->type && $includeDefault) {
            $values = array_replace($this->type->getParameterValues(), $values);
        }

        return $values;
    }

    /**
     * Returns the value of a specific binding parameter.
     *
     * @param string $parameterName  The name of the binding parameter.
     * @param bool   $includeDefault Whether to return the default value set
     *                               in the binding type if no value is set.
     *
     * @return mixed The parameter value.
     *
     * @throws NoSuchParameterException If the parameter does not exist.
     */
    public function getParameterValue($parameterName, $includeDefault = true)
    {
        if (isset($this->parameterValues[$parameterName])) {
            return $this->parameterValues[$parameterName];
        }

        if ($this->type) {
            if ($includeDefault) {
                return $this->type->getParameterValue($parameterName);
            }

            return null;
        }

        throw NoSuchParameterException::forParameterName($parameterName, $this->typeName);
    }

    /**
     * Returns whether the descriptor has any parameter values set.
     *
     * @param bool $includeDefault Whether to include the default values set
     *                             in the binding type.
     *
     * @return bool Returns `true` if any parameter values are set.
     */
    public function hasParameterValues($includeDefault = true)
    {
        if (count($this->parameterValues) > 0) {
            return true;
        }

        if ($this->type && $includeDefault) {
            return $this->type->hasParameterValues();
        }

        return false;
    }

    /**
     * Returns whether the descriptor contains a value for a binding parameter.
     *
     * @param string $parameterName  The name of the binding parameter.
     * @param bool   $includeDefault Whether to include the default values set
     *                               in the binding type.
     *
     * @return bool Returns `true` if a value is set for the parameter.
     */
    public function hasParameterValue($parameterName, $includeDefault = true)
    {
        if (isset($this->parameterValues[$parameterName])) {
            return true;
        }

        if ($this->type && $includeDefault) {
            return $this->type->hasParameterValue($parameterName);
        }

        return false;
    }

    /**
     * Returns the state of the binding.
     *
     * @return int One of the {@link BindingState} constants.
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Resets the state of the binding to unloaded.
     */
    public function resetState()
    {
        $this->type = null;
        $this->violations = array();
        $this->state = BindingState::NOT_LOADED;
    }

    /**
     * Refreshes the state of the binding.
     *
     * @param Package          $package   The package that contains the binding.
     * @param BindingTypeStore $typeStore The store with the defined types.
     */
    public function refreshState(Package $package, BindingTypeStore $typeStore)
    {
        $this->type = null;

        if ($typeStore->existsEnabled($this->typeName)) {
            $validator = new SimpleParameterValidator();

            $this->type = $typeStore->get($this->typeName);
            $this->violations = $validator->validate($this->parameterValues, $this->type->toBindingType());
        }

        $this->state = BindingState::detect($this, $package, $typeStore);
    }

    /**
     * Returns the violations of the binding parameters.
     *
     * @return ConstraintViolation[] The violations.
     */
    public function getViolations()
    {
        return $this->violations;
    }

    /**
     * Returns whether the binding is loaded.
     *
     * @return bool Returns `true` if the state is not
     *              {@link BindingState::NOT_LOADED}.
     *
     * @see BindingState::NOT_LOADED
     */
    public function isLoaded()
    {
        return BindingState::NOT_LOADED !== $this->state;
    }

    /**
     * Returns whether the binding is enabled.
     *
     * @return bool Returns `true` if the state is {@link BindingState::ENABLED}.
     *
     * @see BindingState::ENABLED
     */
    public function isEnabled()
    {
        return BindingState::ENABLED === $this->state;
    }

    /**
     * Returns whether the binding is disabled.
     *
     * @return bool Returns `true` if the state is {@link BindingState::DISABLED}.
     *
     * @see BindingState::DISABLED
     */
    public function isDisabled()
    {
        return BindingState::DISABLED === $this->state;
    }

    /**
     * Returns whether the binding is neither enabled nor disabled.
     *
     * @return bool Returns `true` if the state is {@link BindingState::UNDECIDED}.
     *
     * @see BindingState::UNDECIDED
     */
    public function isUndecided()
    {
        return BindingState::UNDECIDED === $this->state;
    }

    /**
     * Returns whether the binding is held back.
     *
     * @return bool Returns `true` if the state is {@link BindingState::HELD_BACK}.
     *
     * @see BindingState::HELD_BACK
     */
    public function isHeldBack()
    {
        return BindingState::HELD_BACK === $this->state;
    }

    /**
     * Returns whether the binding is ignored.
     *
     * @return bool Returns `true` if the state is {@link BindingState::IGNORED}.
     *
     * @see BindingState::IGNORED
     */
    public function isIgnored()
    {
        return BindingState::IGNORED === $this->state;
    }

    /**
     * Returns whether the binding is invalid.
     *
     * @return bool Returns `true` if the state is {@link BindingState::INVALID}.
     *
     * @see BindingState::INVALID
     */
    public function isInvalid()
    {
        return BindingState::INVALID === $this->state;
    }
}
