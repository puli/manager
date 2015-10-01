<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installer;

use Puli\Manager\Assert\Assert;

/**
 * Describes a resource installer.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallerDescriptor
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $className;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var InstallerParameter[]
     */
    private $parameters = array();

    /**
     * Creates a new installer descriptor.
     *
     * @param string               $name        The installer name.
     * @param string               $className   The fully-qualified class name
     *                                          of the installer.
     * @param string|null          $description The description of the installer.
     * @param InstallerParameter[] $parameters  The installer parameters.
     */
    public function __construct($name, $className, $description = null, array $parameters = array())
    {
        Assert::stringNotEmpty($name, 'The installer name must be a non-empty string. Got: %s');
        Assert::stringNotEmpty($className, 'The installer class must be a non-empty string. Got: %s');
        Assert::nullOrStringNotEmpty($description, 'The installer description must be a non-empty string or null. Got: %s');
        Assert::allIsInstanceOf($parameters, __NAMESPACE__.'\InstallerParameter');

        $this->name = $name;
        $this->className = $className;
        $this->description = $description;

        foreach ($parameters as $parameter) {
            $this->parameters[$parameter->getName()] = $parameter;
        }
    }

    /**
     * Returns the installer name.
     *
     * @return string The name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the fully-qualified class name of the installer.
     *
     * This class does not ensure that the class exists or is an instance of
     * {@link ResourceInstaller}. You should do so in your code.
     *
     * @return string The fully-qualified class name.
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * Returns a human-readable description of the installer.
     *
     * @return string|null The description or `null` if none was set.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the installer parameters.
     *
     * @return InstallerParameter[] The installer parameters.
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Returns an installer parameter.
     *
     * @param string $parameterName The parameter name.
     *
     * @return InstallerParameter The installer parameter.
     *
     * @throws NoSuchParameterException If the parameter does not exist.
     */
    public function getParameter($parameterName)
    {
        if (!isset($this->parameters[$parameterName])) {
            throw NoSuchParameterException::forParameterName($parameterName, $this->name);
        }

        return $this->parameters[$parameterName];
    }

    /**
     * Returns whether the installer has a parameter.
     *
     * @param string $parameterName The parameter name.
     *
     * @return bool Whether the installer has a parameter with that name.
     */
    public function hasParameter($parameterName)
    {
        return isset($this->parameters[$parameterName]);
    }

    /**
     * Returns whether the installer has any parameters.
     *
     * @return bool Returns `true` if the installer has parameters.
     */
    public function hasParameters()
    {
        return count($this->parameters) > 0;
    }

    /**
     * Returns whether the installer has any required parameters.
     *
     * @return bool Returns `true` if the installer has at least one required
     *              parameter and `false` otherwise.
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
     * Returns whether the installer has any optional parameters.
     *
     * @return bool Returns `true` if the installer has at least one optional
     *              parameter and `false` otherwise.
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
     * @throws NoSuchParameterException If the parameter does not exist.
     */
    public function getParameterValue($parameterName)
    {
        return $this->getParameter($parameterName)->getDefaultValue();
    }

    /**
     * Returns whether the installer has any parameters with default values.
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
     * Returns whether the installer has a parameter with a default value.
     *
     * This method checks whether the parameter exists and is optional.
     *
     * @param string $parameterName The parameter name.
     *
     * @return bool Returns `true` if the parameter exists and is optional
     *              (i.e. is not required).
     */
    public function hasParameterValue($parameterName)
    {
        return $this->hasParameter($parameterName) && !$this->getParameter($parameterName)->isRequired();
    }
}
