<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Package\Config;

/**
 * Describes a tag mapping in the package configuration.
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
     * @param string          $puliSelector   The Puli path.
     * @param string|string[] $tags The local paths.
     */
    function __construct($puliSelector, $tags)
    {
        $this->puliSelector = $puliSelector;
        $this->tags = (array) $tags;
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
