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
 * The return value of a {@link Method}.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReturnValue
{
    /**
     * @var string
     */
    private $sourceCode;

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
     * @param string $sourceCode  The source code of the value.
     * @param string $type        The type shown in the doc block.
     * @param string $description The doc block description.
     */
    public function __construct($sourceCode, $type, $description)
    {
        Assert::stringNotEmpty($sourceCode, 'The return value source must be a non-empty string. Got: %s');
        Assert::stringNotEmpty($type, 'The return value type must be a non-empty string. Got: %s');
        Assert::stringNotEmpty($description, 'The return value description must be a non-empty string. Got: %s');

        $this->sourceCode = $sourceCode;
        $this->type = $type;
        $this->description = $description;
    }

    /**
     * Returns the source code of the value.
     *
     * @return string The source code.
     */
    public function getSourceCode()
    {
        return $this->sourceCode;
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
}
