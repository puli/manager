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
 * Maps a puli path to one or more relative file paths.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceDefinition
{
    /**
     * @var string
     */
    private $puliPath;

    /**
     * @var string[]
     */
    private $relativeLocalPaths = array();

    /**
     * Creates a new resource definition.
     *
     * The definition maps a Puli path to one or more file paths relative to
     * the root of the package.
     *
     * @param string   $puliPath   The Puli path.
     * @param string[] $localPaths The local paths.
     */
    function __construct($puliPath, array $localPaths)
    {
        $this->puliPath = $puliPath;
        $this->relativeLocalPaths = $localPaths;
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
    public function getRelativeLocalPaths()
    {
        return $this->relativeLocalPaths;
    }
}
