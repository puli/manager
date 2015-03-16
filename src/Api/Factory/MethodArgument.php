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

use Webmozart\Assert\Assert;

/**
 * An argument of a {@link FactoryMethod}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MethodArgument
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
    private $type;

    /**
     * @var string
     */
    private $description;

    /**
     * Creates a new argument.
     *
     * @param string      $name        The name of the argument.
     * @param string|null $typeHint    The type hint of the argument.
     * @param string      $type        The type shown in the doc block.
     * @param string      $description The doc block description.
     */
    public function __construct($name, $typeHint, $type, $description)
    {
        Assert::stringNotEmpty($name, 'The argument name must be a non-empty string. Got: %s');

        $this->name = $name;

        if (null !== $typeHint) {
            $this->setTypeHint($typeHint);
        }

        $this->setType($type);
        $this->setDescription($description);
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
}
