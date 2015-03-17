<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Php;

use OutOfBoundsException;
use Webmozart\Assert\Assert;

/**
 * A method of a {@link Clazz}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Method
{
    /**
     * @var Clazz
     */
    private $class;

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
    private $body = '';

    /**
     * Creates a new method.
     *
     * @param string $name The method name.
     */
    public function __construct($name)
    {
        Assert::stringNotEmpty($name, 'The method name must be a non-empty string. Got: %s');

        $this->name = $name;
    }

    /**
     * Returns the class this method belongs to.
     *
     * @return Clazz The factory class.
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Sets the class this method belongs to.
     *
     * @param Clazz $class The factory class.
     *
     * @return static The current instance.
     */
    public function setClass(Clazz $class)
    {
        $this->class = $class;

        return $this;
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
     * Returns the method body.
     *
     * @return string The source code.
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Sets the method body.
     *
     * @param string $body The source code.
     *
     * @return static The current instance.
     */
    public function setBody($body)
    {
        Assert::stringNotEmpty($body, 'The method body must be a non-empty string. Got: %s');

        $this->body = $body;

        return $this;
    }

    /**
     * Adds source code to the method body.
     *
     * A newline is inserted between the current code and the added code.
     *
     * @param string $body The source code to add.
     *
     * @return static The current instance.
     */
    public function addBody($body)
    {
        Assert::stringNotEmpty($body, 'The method body must be a non-empty string. Got: %s');

        if ($this->body) {
            $this->body .= "\n".$body;
        } else {
            $this->body = $body;
        }

        return $this;
    }

    /**
     * Clears the method body.
     *
     * @return static The current instance.
     */
    public function clearBody()
    {
        $this->body = '';

        return $this;
    }
}
