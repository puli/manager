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
use Puli\Discovery\Api\Binding\NoSuchParameterException;
use Puli\Discovery\Api\Validation\ConstraintViolation;
use Puli\Discovery\Validation\SimpleParameterValidator;
use Puli\Manager\Api\AlreadyLoadedException;
use Puli\Manager\Api\NotLoadedException;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Util\DistinguishedName;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expression;

/**
 * Describes a resource binding.
 *
 * This class contains a high-level model of {@link ResourceBinding} as it is
 * used in this package.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @see    ResourceBinding
 */
class BindingDescriptor
{
    /**
     * The UUID field in {@link Expression} instances.
     */
    const UUID = 'uuid';

    /**
     * The query field in {@link Expression} instances.
     */
    const QUERY = 'query';

    /**
     * The language field in {@link Expression} instances.
     */
    const LANGUAGE = 'language';

    /**
     * The type name field in {@link Expression} instances.
     */
    const TYPE_NAME = 'typeName';

    /**
     * The parameter values in {@link Expression} instances.
     */
    const PARAMETER_VALUES = 'parameterValues';

    /**
     * The state field in {@link Expression} instances.
     */
    const STATE = 'state';

    /**
     * The package field in {@link Expression} instances.
     */
    const CONTAINING_PACKAGE = 'containingPackage';

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
    private $state;

    /**
     * @var Package
     */
    private $containingPackage;

    /**
     * @var BindingTypeDescriptor
     */
    private $typeDescriptor;

    /**
     * @var ConstraintViolation[]
     */
    private $violations;

    /**
     * Compares two binding descriptors.
     *
     * One binding descriptor is sorted before another if:
     *
     *  * its query is lexicographically smaller;
     *  * the queries are the same but the type is lexicographically smaller.
     *
     * @param BindingDescriptor $a The first descriptor to compare.
     * @param BindingDescriptor $b The second descriptor to compare.
     *
     * @return int Returns a negative value if `$a` is sorted before `$b` and
     *             a positive value if `$b` is sorted before `$a`. If 0 is
     *             returned, `$a` and `$b` are sorted to the same position.
     */
    public static function compare(self $a, self $b)
    {
        if ($a->query === $b->query) {
            return strcmp($a->typeName, $b->typeName);
        }

        return strcmp($a->query, $b->query);
    }

    /**
     * Creates a new binding descriptor.
     *
     * @param string $query           The query for the resources of the binding.
     * @param string $typeName        The name of the binding type.
     * @param array  $parameterValues The values of the binding parameters.
     * @param string $language        The language of the query.
     * @param Uuid   $uuid            The UUID of the binding. If no UUID is
     *                                passed, a UUID is generated.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     *
     * @see ResourceBinding
     */
    public function __construct($query, $typeName, array $parameterValues = array(), $language = 'glob', Uuid $uuid = null)
    {
        Assert::query($query);
        Assert::typeName($typeName);
        Assert::language($language);
        Assert::allParameterName(array_keys($parameterValues));
        Assert::allParameterValue($parameterValues);

        if (null === $uuid) {
            $uuid = Uuid::uuid4();
        }

        $this->uuid = $uuid;
        $this->query = $query;
        $this->language = $language;
        $this->typeName = $typeName;
        $this->parameterValues = $parameterValues;
    }

    /**
     * Loads the binding descriptor.
     *
     * @param Package               $containingPackage The package that contains
     *                                                 the descriptor.
     * @param BindingTypeDescriptor $typeDescriptor    The type descriptor.
     *
     * @throws AlreadyLoadedException If the descriptor is already loaded.
     */
    public function load(Package $containingPackage, BindingTypeDescriptor $typeDescriptor = null)
    {
        if (null !== $this->state) {
            throw new AlreadyLoadedException('The binding descriptor is already loaded.');
        }

        if ($typeDescriptor && $this->typeName !== $typeDescriptor->getName()) {
            throw new InvalidArgumentException(sprintf(
                'The passed type "%s" does not match the stored type name "%s".',
                $typeDescriptor->getName(),
                $this->typeName
            ));
        }

        $this->violations = array();

        if ($typeDescriptor) {
            $validator = new SimpleParameterValidator();
            $bindingType = $typeDescriptor->toBindingType();

            $this->violations = $validator->validate($this->parameterValues, $bindingType);
        }

        $this->containingPackage = $containingPackage;
        $this->typeDescriptor = $typeDescriptor;

        $this->refreshState();
    }

    /**
     * Unloads the binding descriptor.
     *
     * All memory allocated during {@link load()} is freed.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function unload()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        $this->containingPackage = null;
        $this->typeDescriptor = null;
        $this->violations = null;
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

        if ($this->typeDescriptor && $includeDefault) {
            $values = array_replace($this->typeDescriptor->getParameterValues(), $values);
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

        if ($this->typeDescriptor) {
            if ($includeDefault) {
                return $this->typeDescriptor->getParameterValue($parameterName);
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

        if ($this->typeDescriptor && $includeDefault) {
            return $this->typeDescriptor->hasParameterValues();
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

        if ($this->typeDescriptor && $includeDefault) {
            return $this->typeDescriptor->hasParameterValue($parameterName);
        }

        return false;
    }

    /**
     * Returns the violations of the binding parameters.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return ConstraintViolation[] The violations.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getViolations()
    {
        if (null === $this->violations) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->violations;
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
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->containingPackage;
    }

    /**
     * Returns the type descriptor.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return BindingTypeDescriptor|null The type descriptor or null, if no
     *                                    type descriptor exists for the
     *                                    binding's type name.
     *
     * @throws NotLoadedException If the binding descriptor is not loaded.
     */
    public function getTypeDescriptor()
    {
        // Check containing package, as the type descriptor may be null
        if (null === $this->containingPackage) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->typeDescriptor;
    }

    /**
     * Returns the state of the binding.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return int One of the {@link BindingState} constants.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     */
    public function getState()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return $this->state;
    }

    /**
     * Returns whether the binding is enabled.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::ENABLED}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::ENABLED
     */
    public function isEnabled()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::ENABLED === $this->state;
    }

    /**
     * Returns whether the binding is disabled.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::DISABLED}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::DISABLED
     */
    public function isDisabled()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::DISABLED === $this->state;
    }

    /**
     * Returns whether the binding is neither enabled nor disabled.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::UNDECIDED}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::UNDECIDED
     */
    public function isUndecided()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::UNDECIDED === $this->state;
    }

    /**
     * Returns whether the binding is held back.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::HELD_BACK}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::HELD_BACK
     */
    public function isHeldBack()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::HELD_BACK === $this->state;
    }

    /**
     * Returns whether the binding is invalid.
     *
     * The method {@link load()} needs to be called before calling this method,
     * otherwise an exception is thrown.
     *
     * @return bool Returns `true` if the state is {@link BindingState::INVALID}.
     *
     * @throws NotLoadedException If the descriptor is not loaded.
     *
     * @see BindingState::INVALID
     */
    public function isInvalid()
    {
        if (null === $this->state) {
            throw new NotLoadedException('The binding descriptor is not loaded.');
        }

        return BindingState::INVALID === $this->state;
    }

    /**
     * Returns whether the binding matches the given expression.
     *
     * @param Expression $expr The search criteria. You can use the fields
     *                         {@link UUID}, {@link QUERY}, {@link LANGUAGE},
     *                         {@link TYPE_NAME}, {@link STATE} and
     *                         {@link CONTAINING_PACKAGE} in the expression.
     *
     * @return bool Returns `true` if the binding matches the expression and
     *              `false` otherwise.
     */
    public function match(Expression $expr)
    {
        return $expr->evaluate(array(
            self::UUID => $this->uuid->toString(),
            self::QUERY => $this->query,
            self::LANGUAGE => $this->language,
            self::TYPE_NAME => $this->typeName,
            self::PARAMETER_VALUES => $this->parameterValues,
            self::STATE => $this->state,
            self::CONTAINING_PACKAGE => $this->containingPackage->getName(),
        ));
    }

    private function refreshState()
    {
        if (null === $this->typeDescriptor || !$this->typeDescriptor->isLoaded()
            || !$this->typeDescriptor->isEnabled()) {
            $this->state = BindingState::HELD_BACK;
        } elseif (count($this->violations) > 0) {
            $this->state = BindingState::INVALID;
        } elseif ($this->containingPackage instanceof RootPackage) {
            $this->state = BindingState::ENABLED;
        } elseif ($this->containingPackage->getInstallInfo()->hasDisabledBindingUuid($this->uuid)) {
            $this->state = BindingState::DISABLED;
        } elseif ($this->containingPackage->getInstallInfo()->hasEnabledBindingUuid($this->uuid)) {
            $this->state = BindingState::ENABLED;
        } else {
            $this->state = BindingState::UNDECIDED;
        }
    }
}
