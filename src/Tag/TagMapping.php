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
 * Maps a Puli selector to a tag.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagMapping
{
    /**
     * @var string
     */
    private $puliSelector;

    /**
     * @var string
     */
    private $tag;

    /**
     * Creates a new tag mapping.
     *
     * The mapping maps a Puli selector to one or more tags. The Puli
     * selector can be a Puli path or a pattern containing wildcards.
     *
     * @param string $puliSelector The Puli selector. Must be a non-empty string.
     * @param string $tag          The tag.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($puliSelector, $tag)
    {
        if (!is_string($puliSelector)) {
            throw new InvalidArgumentException(sprintf(
                'The Puli selector must be a string. Got: %s',
                is_object($puliSelector) ? get_class($puliSelector) : gettype($puliSelector)
            ));
        }

        if ('' === $puliSelector) {
            throw new InvalidArgumentException('The Puli selector must not be empty.');
        }

        if (!is_string($tag)) {
            throw new InvalidArgumentException(sprintf(
                'The tag must be a string. Got: %s',
                is_object($tag) ? get_class($tag) : gettype($tag)
            ));
        }

        if ('' === $tag) {
            throw new InvalidArgumentException('The tag must not be empty.');
        }

        $this->puliSelector = $puliSelector;
        $this->tag = $tag;
    }

    /**
     * Returns the Puli selector.
     *
     * The Puli selector can be a Puli path or a pattern containing wildcards.
     *
     * @return string The Puli selector.
     */
    public function getPuliSelector()
    {
        return $this->puliSelector;
    }

    /**
     * Returns the tag.
     *
     * @return string The tag.
     */
    public function getTag()
    {
        return $this->tag;
    }
}
