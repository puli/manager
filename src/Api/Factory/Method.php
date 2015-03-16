<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Factory;

use OutOfBoundsException;
use Webmozart\Assert\Assert;

/**
 * A method of a {@link FactoryClass}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Method
{
    /**
     * @var FactoryClass
     */
    private $factoryClass;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
     * @var Argument[]
     */
    private $arguments = array();

    /**
     * @var ReturnValue|null
     */
    private $returnValue;

    /**
     * @var string
     */
    private $sourceCode = '';

    /**
     * Creates a new method.
     *
     * @param FactoryClass $factoryClass The class this method belongs to.
     * @param string       $name         The method name.
     */
    public function __construct(FactoryClass $factoryClass, $name)
    {
        Assert::stringNotEmpty($name, 'The method name must be a non-empty string. Got: %s');

        $this->factoryClass = $factoryClass;
        $this->name = $name;
    }

    /**
     * Returns the class this method belongs to.
     *
     * @return FactoryClass The factory class.
     */
    public function getFactoryClass()
    {
        return $this->factoryClass;
    }

    /**
     * Returns the name of the method.
     *
     * @return string The method name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the description of the method.
     *
     * @return string The method description.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the description of the method.
     *
     * @param string $description The method description.
     *
     * @return static The current instance.
     */
    public function setDescription($description)
    {
        Assert::stringNotEmpty($description, 'The method description must be a non-empty string. Got: %s');

        $this->description = $description;

        return $this;
    }

    /**
     * Returns the method arguments.
     *
     * @return Argument[] The arguments indexed by their names.
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Returns the argument with the given name.
     *
     * @param string $name The argument name.
     *
     * @return Argument The argument.
     *
     * @throws OutOfBoundsException If the argument does not exist.
     */
    public function getArgument($name)
    {
        if (!isset($this->arguments[$name])) {
            throw new OutOfBoundsException(sprintf(
                'The argument "%s" does not exist.',
                $name
            ));
        }

        return $this->arguments[$name];
    }

    /**
     * Returns whether the method has any arguments.
     *
     * @return bool Returns `true` if the method has arguments and `false`
     *              otherwise.
     */
    public function hasArguments()
    {
        return count($this->arguments) > 0;
    }

    /**
     * Returns whether the argument with the given name exist.
     *
     * @param string $name The argument name.
     *
     * @return bool Returns `true` if an argument with that name exists and
     *              `false` otherwise.
     */
    public function hasArgument($name)
    {
        return isset($this->arguments[$name]);
    }

    /**
     * Sets the arguments of the method.
     *
     * Existing arguments are overwritten.
     *
     * @param Argument[] $arguments The arguments to set.
     *
     * @return static The current instance.
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = array();

        $this->addArguments($arguments);

        return $this;
    }

    /**
     * Adds arguments to the method.
     *
     * Existing arguments are kept.
     *
     * @param Argument[] $arguments The arguments to add.
     *
     * @return static The current instance.
     */
    public function addArguments(array $arguments)
    {
        foreach ($arguments as $argument) {
            $this->addArgument($argument);
        }

        return $this;
    }

    /**
     * Adds an argument to the method.
     *
     * @param Argument $argument The argument to add.
     *
     * @return static The current instance.
     */
    public function addArgument(Argument $argument)
    {
        $this->arguments[$argument->getName()] = $argument;

        return $this;
    }

    /**
     * Removes an argument from the method.
     *
     * If the argument does not exist, this method does nothing.
     *
     * @param string $name The name of the argument.
     *
     * @return static The current instance.
     */
    public function removeArgument($name)
    {
        unset($this->arguments[$name]);

        return $this;
    }

    /**
     * Removes all arguments.
     *
     * @return static The current instance.
     */
    public function clearArguments()
    {
        $this->arguments = array();

        return $this;
    }

    /**
     * Returns the return value of the method.
     *
     * @return ReturnValue|null The return value or `null` if the method has
     *                          no return value.
     */
    public function getReturnValue()
    {
        return $this->returnValue;
    }

    /**
     * Sets the return value of the method.
     *
     * @param ReturnValue $returnValue The return value.
     *
     * @return static The current instance.
     */
    public function setReturnValue(ReturnValue $returnValue)
    {
        $this->returnValue = $returnValue;

        return $this;
    }

    /**
     * Returns whether the method has a return value.
     *
     * @return bool Returns `true` if the method has a return value and `false`
     *              otherwise.
     */
    public function hasReturnValue()
    {
        return null !== $this->returnValue;
    }

    /**
     * Removes the return value of the method.
     *
     * If the method has no return value, this method does nothing.
     *
     * @return static The current instance.
     */
    public function removeReturnValue()
    {
        $this->returnValue = null;

        return $this;
    }

    /**
     * Returns the source code of the method.
     *
     * @return string The source code.
     */
    public function getSourceCode()
    {
        return $this->sourceCode;
    }

    /**
     * Sets the source code of the method.
     *
     * @param string $sourceCode The source code.
     *
     * @return static The current instance.
     */
    public function setSourceCode($sourceCode)
    {
        Assert::stringNotEmpty($sourceCode, 'The method source code must be a non-empty string. Got: %s');

        $this->sourceCode = trim($sourceCode);

        return $this;
    }

    /**
     * Adds source code to the method.
     *
     * A newline is inserted between the current code and the added code.
     *
     * @param string $sourceCode The source code to add.
     *
     * @return static The current instance.
     */
    public function addSourceCode($sourceCode)
    {
        Assert::stringNotEmpty($sourceCode, 'The method source code must be a non-empty string. Got: %s');

        $sourceCode = trim($sourceCode);

        if ($this->sourceCode) {
            $this->sourceCode .= "\n".$sourceCode;
        } else {
            $this->sourceCode = $sourceCode;
        }

        return $this;
    }

    /**
     * Clears the source code of the method.
     *
     * @return static The current instance.
     */
    public function clearSourceCode()
    {
        $this->sourceCode = '';

        return $this;
    }
}
