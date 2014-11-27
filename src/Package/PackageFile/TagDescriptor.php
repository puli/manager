<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile;

/**
 * Describes a tag mapping in the package file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagDescriptor
{
    /**
     * @var string
     */
    private $puliSelector;

    /**
     * @var string[]
     */
    private $tags = array();

    /**
     * Creates a new tag descriptor.
     *
     * The descriptor maps a Puli selector to one or more tags. The Puli
     * selector can be a Puli path or a pattern containing wildcards.
     *
     * @param string          $puliSelector   The Puli path. Must be a non-empty
     *                                        string.
     * @param string|string[] $tags           The local paths. Must be one or
     *                                        more non-empty strings.
     *
     * @throws \InvalidArgumentException If any of the arguments is invalid.
     */
    function __construct($puliSelector, $tags)
    {
        if (!is_string($puliSelector)) {
            throw new \InvalidArgumentException(sprintf(
                'The passed Puli selector must be a string. Got: %s',
                is_object($puliSelector) ? get_class($puliSelector) : gettype($puliSelector)
            ));
        }

        if ('' === $puliSelector) {
            throw new \InvalidArgumentException('The passed Puli selector must not be empty.');
        }

        $tags = (array) $tags;

        if (0 === count($tags)) {
            throw new \InvalidArgumentException('At least one tag must be passed.');
        }

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new \InvalidArgumentException(sprintf(
                    'The passed tags must be strings. Got: %s',
                    is_object($tag) ? get_class($tag) : gettype($tag)
                ));
            }

            if ('' === $tag) {
                throw new \InvalidArgumentException('The passed tags must not be empty.');
            }
        }

        $this->puliSelector = $puliSelector;
        $this->tags = $tags;
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
     * Returns the tags.
     *
     * @return string[] The tags.
     */
    public function getTags()
    {
        return $this->tags;
    }
}
