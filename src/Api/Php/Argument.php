<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Php;

use Webmozart\Assert\Assert;

/**
 * An argument of a {@link Method}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Argument
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $typeHint;

    /**
     * @var string
     */
    private $type = 'mixed';

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $defaultValue;

    /**
     * Creates a new argument.
     *
     * @param string $name The name of the argument.
     */
    public function __construct($name)
    {
        Assert::stringNotEmpty($name, 'The argument name must be a non-empty string. Got: %s');

        $this->name = $name;
    }

    /**
     * Returns the name of the argument.
     *
     * @return string The argument name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the type hint of the argument.
     *
     * @return string|null The type hint or `null` if the argument has no type
     *                     hint.
     */
    public function getTypeHint()
    {
        return $this->typeHint;
    }

    /**
     * Returns whether the argument has a type hint.
     *
     * @return bool Returns `true` if the argument has a type hint and `false`
     *              otherwise.
     */
    public function hasTypeHint()
    {
        return null !== $this->typeHint;
    }

    /**
     * Sets the type hint of the argument.
     *
     * @param string $typeHint The type hint.
     *
     * @return static The current instance.
     */
    public function setTypeHint($typeHint)
    {
        Assert::stringNotEmpty($typeHint, 'The argument type hint must be a non-empty string. Got: %s');

        $this->typeHint = $typeHint;

        return $this;
    }

    /**
     * Removes the type hint of the argument.
     *
     * @return static The current instance.
     */
    public function removeTypeHint()
    {
        $this->typeHint = null;

        return $this;
    }

    /**
     * Returns the type of the argument.
     *
     * @return string The argument type.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the type of the argument.
     *
     * @param string $type The argument type.
     *
     * @return static The current instance.
     */
    public function setType($type)
    {
        Assert::stringNotEmpty($type, 'The argument type must be a non-empty string. Got: %s');

        $this->type = $type;

        return $this;
    }

    /**
     * Returns the description of the argument.
     *
     * @return string The argument description.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the description of the argument.
     *
     * @param string $description The argument description.
     *
     * @return static The current instance.
     */
    public function setDescription($description)
    {
        Assert::stringNotEmpty($description, 'The argument description must be a non-empty string. Got: %s');

        $this->description = $description;

        return $this;
    }

    /**
     * Returns the default value of the argument.
     *
     * @return string|null The default value as source code or `null` if the
     *                     argument has no default value.
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * Sets the default value of the argument.
     *
     * @param string $defaultValue The default value as source code.
     *
     * @return static The current instance.
     */
    public function setDefaultValue($defaultValue)
    {
        Assert::stringNotEmpty($defaultValue, 'The argument default value must be a non-empty string. Got: %s');

        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * Returns whether the argument has a default value.
     *
     * @return bool Returns `true` if the argument has a default value and
     *              `false` otherwise.
     */
    public function hasDefaultValue()
    {
        return null !== $this->defaultValue;
    }

    /**
     * Removes the default value of the argument.
     *
     * If the argument has no default value, this method does nothing.
     *
     * @return static The current instance.
     */
    public function removeDefaultValue()
    {
        $this->defaultValue = null;

        return $this;
    }

    /**
     * Returns the source code of the argument.
     *
     * @return string The source code.
     */
    public function __toString()
    {
        $string = '$'.$this->name;

        if ($this->typeHint) {
            $string = $this->typeHint.' '.$string;
        }

        if ($this->defaultValue) {
            $string .= ' = '.$this->defaultValue;
        }

        return $string;
    }
}
