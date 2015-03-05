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
use Rhumsaa\Uuid\Uuid;

/**
 * Criteria for searching {@link BindingDescriptor} instances.
 *
 * You can match binding descriptors against the criteria with
 * {@link BindingDescriptor::match()}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingCriteria
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
     * @var string
     */
    private $uuidPrefix;

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
     * of the package that defines the binding.
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
     * Returns the accepted binding states.
     *
     * @return int[] An array of {@link BindingState} constants.
     *
     * @see matchState()
     */
    public function getStates()
    {
        return array_keys($this->states);
    }

    /**
     * Sets the accepted binding states.
     *
     * Previously set binding states are overwritten.
     *
     * @param int[] $states An array of {@link BindingState} constants.
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
     * Adds accepted binding states.
     *
     * Previously set binding states are kept.
     *
     * @param int[] $states An array of {@link BindingState} constants.
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
     * Adds an accepted binding state.
     *
     * Previously set binding states are kept.
     *
     * @param int $state A {@link BindingState} constant.
     *
     * @return static The current instance.
     *
     * @see matchState()
     */
    public function addState($state)
    {
        Assert::oneOf($state, BindingState::all(), 'The binding state must be one of the BindingState constants. Got: "%s"');

        $this->states[$state] = true;

        return $this;
    }

    /**
     * Removes an accepted binding state.
     *
     * If the binding state was not set on the criteria, this method does nothing.
     *
     * @param int $state A {@link BindingState} constant.
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
     * Clears the binding states of the criteria.
     *
     * After calling this method, bindings will match the criteria independent
     * of their state.
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
     * Returns whether a binding state matches the criteria.
     *
     * A binding state matches if:
     *
     *  * The criteria defines no binding states.
     *  * The binding state is one of the defined states.
     *
     * @param int $state The {@link BindingState} constant to test.
     *
     * @return bool Returns `true` if the binding state matches the criteria and
     *              `false` otherwise.
     */
    public function matchState($state)
    {
        if (!$this->states) {
            return true;
        }

        return isset($this->states[$state]);
    }

    /**
     * Returns the accepted UUID prefix.
     *
     * @return string The UUID prefix.
     *
     * @see matchUuid()
     */
    public function getUuidPrefix()
    {
        return $this->uuidPrefix;
    }

    /**
     * Sets the accepted UUID prefix.
     *
     * @param string $uuidPrefix The UUID prefix.
     *
     * @return static The current instance.
     *
     * @see matchUuid()
     */
    public function setUuidPrefix($uuidPrefix)
    {
        Assert::string($uuidPrefix, 'The UUID prefix must be a string. Got: %s');
        Assert::notEmpty($uuidPrefix, 'The UUID prefix must not be empty.');

        $this->uuidPrefix = $uuidPrefix;

        return $this;
    }

    /**
     * Clears the UUID prefix of the criteria.
     *
     * After calling this method, bindings will match the criteria independent
     * of their UUID.
     *
     * @return static The current instance.
     *
     * @see matchUuid()
     */
    public function clearUuidPrefix()
    {
        $this->uuidPrefix = null;

        return $this;
    }

    /**
     * Returns whether a UUID matches the criteria.
     *
     * A UUID matches if:
     *
     *  * The criteria defines no UUID prefix.
     *  * The passed UUID has the defined UUID prefix.
     *
     * @param Uuid $uuid The UUID to test.
     *
     * @return bool Returns `true` if the UUID matches the criteria and `false`
     *              otherwise.
     */
    public function matchUuid(Uuid $uuid)
    {
        if (!$this->uuidPrefix) {
            return true;
        }

        return 0 === strpos($uuid->toString(), $this->uuidPrefix);
    }

    /**
     * Returns the accepted query.
     *
     * @return string The query.
     *
     * @see matchQuery()
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Sets the accepted query.
     *
     * @param string $query The query.
     *
     * @return static The current instance.
     *
     * @see matchQuery()
     */
    public function setQuery($query)
    {
        Assert::string($query, 'The query must be a string. Got: %s');
        Assert::notEmpty($query, 'The query must not be empty.');

        $this->query = $query;

        return $this;
    }

    /**
     * Clears the query of the criteria.
     *
     * After calling this method, bindings will match the criteria independent
     * of their query.
     *
     * @return static The current instance.
     *
     * @see matchQuery()
     */
    public function clearQuery()
    {
        $this->query = null;

        return $this;
    }

    /**
     * Returns whether a query matches the criteria.
     *
     * A query matches if:
     *
     *  * The criteria defines no query.
     *  * The passed query equals the defined query.
     *
     * @param string $query The query to test.
     *
     * @return bool Returns `true` if the query matches the criteria and `false`
     *              otherwise.
     */
    public function matchQuery($query)
    {
        if (!$this->query) {
            return true;
        }

        return $query === $this->query;
    }

    /**
     * Returns the accepted query language.
     *
     * @return string The query language.
     *
     * @see matchLanguage()
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Sets the accepted query language.
     *
     * @param string $language The query language.
     *
     * @return static The current instance.
     *
     * @see matchLanguage()
     */
    public function setLanguage($language)
    {
        Assert::string($language, 'The language must be a string. Got: %s');
        Assert::notEmpty($language, 'The language must not be empty.');

        $this->language = $language;

        return $this;
    }

    /**
     * Clears the language of the criteria.
     *
     * After calling this method, bindings will match the criteria independent
     * of their query language.
     *
     * @return static The current instance.
     *
     * @see matchLanguage()
     */
    public function clearLanguage()
    {
        $this->language = null;

        return $this;
    }

    /**
     * Returns whether a query language matches the criteria.
     *
     * A query language matches if:
     *
     *  * The criteria defines no query language.
     *  * The passed query language equals the defined language.
     *
     * @param string $language The query language to test.
     *
     * @return bool Returns `true` if the query language matches the criteria
     *              and `false` otherwise.
     */
    public function matchLanguage($language)
    {
        if (!$this->language) {
            return true;
        }

        return $language === $this->language;
    }

    /**
     * Returns the accepted type name.
     *
     * @return string The type name.
     *
     * @see matchTypeName()
     */
    public function getTypeName()
    {
        return $this->typeName;
    }

    /**
     * Sets the accepted query type name.
     *
     * @param string $typeName The type name.
     *
     * @return static The current instance.
     *
     * @see matchTypeName()
     */
    public function setTypeName($typeName)
    {
        Assert::string($typeName, 'The type name must be a string. Got: %s');
        Assert::notEmpty($typeName, 'The type name must not be empty.');

        $this->typeName = $typeName;

        return $this;
    }

    /**
     * Clears the type name of the criteria.
     *
     * After calling this method, bindings will match the criteria independent
     * of their type name.
     *
     * @return static The current instance.
     *
     * @see matchTypeName()
     */
    public function clearTypeName()
    {
        $this->typeName =  null;

        return $this;
    }

    /**
     * Returns whether a type name matches the criteria.
     *
     * A type name matches if:
     *
     *  * The criteria defines no type name.
     *  * The passed type name equals the defined type name.
     *
     * @param string $typeName The type name to test.
     *
     * @return bool Returns `true` if the type name matches the criteria and
     *              `false` otherwise.
     */
    public function matchTypeName($typeName)
    {
        if (!$this->typeName) {
            return true;
        }

        return $typeName === $this->typeName;
    }
}
