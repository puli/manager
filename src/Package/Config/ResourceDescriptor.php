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
 * Describes a resource mapping in the package configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceDescriptor
{
    /**
     * @var string
     */
    private $puliPath;

    /**
     * @var string[]
     */
    private $localPaths = array();

    /**
     * Creates a new resource descriptor.
     *
     * The descriptor maps a Puli path to one or more file paths relative to
     * the root of the package.
     *
     * @param string          $puliPath   The Puli path.
     * @param string|string[] $localPaths The local paths.
     */
    function __construct($puliPath, $localPaths)
    {
        $this->puliPath = $puliPath;
        $this->localPaths = (array) $localPaths;
    }

    /**
     * Returns the Puli path.
     *
     * @return string The Puli path.
     */
    public function getPuliPath()
    {
        return $this->puliPath;
    }

    /**
     * Returns the local paths the Puli path is mapped to.
     *
     * The paths are relative to the root of the Puli package.
     *
     * @return string[] The relative local paths.
     */
    public function getLocalPaths()
    {
        return $this->localPaths;
    }
}
