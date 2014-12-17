<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tag;

use InvalidArgumentException;

/**
 * Defines a tag.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagDefinition
{
    /**
     * @var string
     */
    private $tag;

    /**
     * @var string
     */
    private $description;

    /**
     * Creates a new tag definition.
     *
     * The tag definition describes what a tag is used for. Only tags that have
     * been defined by a package are actually added to the resource repository.
     *
     * @param string      $tag         The name of the tag.
     * @param string|null $description Describes what the tag is used for.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($tag, $description = null)
    {
        if (!is_string($tag)) {
            throw new InvalidArgumentException(sprintf(
                'The tag name must be a string. Got: %s',
                is_object($tag) ? get_class($tag) : gettype($tag)
            ));
        }

        if ('' === $tag) {
            throw new InvalidArgumentException('The tag name must not be empty.');
        }

        if (!is_string($description) && $description !== null) {
            throw new InvalidArgumentException(sprintf(
                'The tag description must be a string or null. Got: %s',
                is_object($description) ? get_class($description) : gettype($description)
            ));
        }

        if ('' === $description) {
            throw new InvalidArgumentException('The tag description must not be empty.');
        }

        $this->tag = $tag;
        $this->description = $description;
    }

    /**
     * Returns the tag name.
     *
     * @return string The tag name.
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Returns the description of the tag.
     *
     * The description should indicate what the tag is used for.
     *
     * @return string|null The tag description or `null` if none is available.
     */
    public function getDescription()
    {
        return $this->description;
    }


}
