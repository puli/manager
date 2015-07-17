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
 * The return value of a {@link Method}.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReturnValue
{
    /**
     * @var string
     */
    private $value;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $description;

    /**
     * Creates a new return value.
     *
     * @param string $value       The value as source code.
     * @param string $type        The type shown in the doc block.
     * @param string $description The doc block description.
     */
    public function __construct($value, $type = 'mixed', $description = null)
    {
        Assert::stringNotEmpty($value, 'The return value must be a non-empty string. Got: %s');
        Assert::stringNotEmpty($type, 'The return value type must be a non-empty string. Got: %s');
        Assert::nullOrStringNotEmpty($description, 'The return value description must be a non-empty string or null. Got: %s');

        $this->value = $value;
        $this->type = $type;
        $this->description = $description;
    }

    /**
     * Returns the the value.
     *
     * @return string The source code of the value.
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the type of the return value.
     *
     * @return string The return type.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the description of the return value.
     *
     * @return string The return value description.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the source code of the return value.
     *
     * @return string The source code.
     */
    public function __toString()
    {
        return $this->value;
    }
}
