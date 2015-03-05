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

use Puli\RepositoryManager\Assert\Assert;

/**
 * Criteria for searching {@link BindingTypeDescriptor} instances.
 *
 * You can match binding type descriptors against the criteria with
 * {@link BindingTypeDescriptor::match()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingTypeCriteria
{
    /**
     * @var string[]
     */
    private $packageNames = array();

    /**
     * @var int[]
     */
    private $states = array();

    /**
     * Creates a new criteria instance.
     *
     * @return static The created criteria.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Returns the accepted package names.
     *
     * @return string[] The accepted package names.
     *
     * @see matchPackageName()
     */
    public function getPackageNames()
    {
        return array_keys($this->packageNames);
    }

    /**
     * Sets the accepted package names.
     *
     * Previously set package names are overwritten.
     *
     * @param string[] $packageNames The package names.
     *
     * @return static The current instance.
     *
     * @see matchPackageName()
     */
    public function setPackageNames(array $packageNames)
    {
        $this->packageNames = array();

        $this->addPackageNames($packageNames);

        return $this;
    }

    /**
     * Adds accepted package names.
     *
     * Previously set package names are kept.
     *
     * @param string[] $packageNames The package names.
     *
     * @return static The current instance.
     *
     * @see matchPackageName()
     */
    public function addPackageNames(array $packageNames)
    {
        foreach ($packageNames as $packageName) {
            $this->addPackageName($packageName);
        }

        return $this;
    }

    /**
     * Adds an accepted package name.
     *
     * Previously set package names are kept.
     *
     * @param string $packageName The package name.
     *
     * @return static The current instance.
     *
     * @see matchPackageName()
     */
    public function addPackageName($packageName)
    {
        Assert::string($packageName, 'The package name must be a string. Got: %s');
        Assert::notEmpty($packageName, 'The package name must not be empty.');

        $this->packageNames[$packageName] = true;

        return $this;
    }

    /**
     * Removes an accepted package name.
     *
     * If the package name was not set on the criteria, this method does nothing.
     *
     * @param string $packageName The package name to remove.
     *
     * @return static The current instance.
     *
     * @see matchPackageName()
     */
    public function removePackageName($packageName)
    {
        unset($this->packageNames[$packageName]);

        return $this;
    }

    /**
     * Clears the package names of the criteria.
     *
     * After calling this method, bindings will match the criteria independent
     * of the package that defines the binding type.
     *
     * @return static The current instance.
     *
     * @see matchPackageName()
     */
    public function clearPackageNames()
    {
        $this->packageNames = array();

        return $this;
    }

    /**
     * Returns whether a package name matches the criteria.
     *
     * A package name matches if:
     *
     *  * The criteria defines no package names.
     *  * The package name is one of the defined package names.
     *
     * @param string $packageName The package name to test.
     *
     * @return bool Returns `true` if the package name matches the criteria and
     *              `false` otherwise.
     */
    public function matchPackageName($packageName)
    {
        if (!$this->packageNames) {
            return true;
        }

        return isset($this->packageNames[$packageName]);
    }

    /**
     * Returns the accepted binding type states.
     *
     * @return int[] An array of {@link BindingTypeState} constants.
     *
     * @see matchState()
     */
    public function getStates()
    {
        return array_keys($this->states);
    }

    /**
     * Sets the accepted binding type states.
     *
     * Previously set binding type states are overwritten.
     *
     * @param int[] $states An array of {@link BindingTypeState} constants.
     *
     * @return static The current instance.
     *
     * @see matchState()
     */
    public function setStates(array $states)
    {
        $this->states = array();

        $this->addStates($states);

        return $this;
    }

    /**
     * Adds accepted binding type states.
     *
     * Previously set binding type states are kept.
     *
     * @param int[] $states An array of {@link BindingTypeState} constants.
     *
     * @return static The current instance.
     *
     * @see matchState()
     */
    public function addStates(array $states)
    {
        foreach ($states as $state) {
            $this->addState($state);
        }

        return $this;
    }

    /**
     * Adds an accepted binding type state.
     *
     * Previously set binding type states are kept.
     *
     * @param int $state A {@link BindingTypeState} constant.
     *
     * @return static The current instance.
     *
     * @see matchState()
     */
    public function addState($state)
    {
        Assert::oneOf($state, BindingTypeState::all(), 'The binding type state must be one of the BindingTypeState constants. Got: "%s"');

        $this->states[$state] = true;

        return $this;
    }

    /**
     * Removes an accepted binding type state.
     *
     * If the binding type state was not set on the criteria, this method does
     * nothing.
     *
     * @param int $state A {@link BindingTypeState} constant.
     *
     * @return static The current instance.
     *
     * @see matchState()
     */
    public function removeState($state)
    {
        unset($this->states[$state]);

        return $this;
    }

    /**
     * Clears the binding type states of the criteria.
     *
     * After calling this method, binding types will match the criteria
     * independent of their state.
     *
     * @return static The current instance.
     *
     * @see matchState()
     */
    public function clearStates()
    {
        $this->states = array();

        return $this;
    }

    /**
     * Returns whether a binding type state matches the criteria.
     *
     * A binding type state matches if:
     *
     *  * The criteria defines no binding type states.
     *  * The binding type state is one of the defined states.
     *
     * @param int $state The {@link BindingTypeState} constant to test.
     *
     * @return bool Returns `true` if the binding type state matches the
     *              criteria and `false` otherwise.
     */
    public function matchState($state)
    {
        if (!$this->states) {
            return true;
        }

        return isset($this->states[$state]);
    }
}
