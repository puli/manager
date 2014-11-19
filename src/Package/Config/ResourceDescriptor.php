<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Config;

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
        if (!is_string($puliPath)) {
            throw new \InvalidArgumentException(sprintf(
                'The passed Puli path must be a string. Got: %s',
                is_object($puliPath) ? get_class($puliPath) : gettype($puliPath)
            ));
        }

        if ('' === $puliPath) {
            throw new \InvalidArgumentException('The passed Puli path must not be empty.');
        }

        $localPaths = (array) $localPaths;

        if (0 === count($localPaths)) {
            throw new \InvalidArgumentException('At least one local path must be passed.');
        }

        foreach ($localPaths as $localPath) {
            if (!is_string($localPath)) {
                throw new \InvalidArgumentException(sprintf(
                    'The passed local paths must be strings. Got: %s',
                    is_object($localPath) ? get_class($localPath) : gettype($localPath)
                ));
            }

            if ('' === $localPath) {
                throw new \InvalidArgumentException('The passed local paths must not be empty.');
            }
        }

        $this->puliPath = $puliPath;
        $this->localPaths = $localPaths;
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
