<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package;

/**
 * Maps a Puli path to one or more local paths.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceMapping
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
     * Creates a new resource mapping.
     *
     * The mapping maps a Puli path to one or more file paths relative to the
     * root of the package.
     *
     * @param string          $puliPath   The Puli path.
     * @param string|string[] $localPaths The local paths.
     *
     * @throws \InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($puliPath, $localPaths)
    {
        if (!is_string($puliPath)) {
            throw new \InvalidArgumentException(sprintf(
                'The Puli path must be a string. Got: %s',
                is_object($puliPath) ? get_class($puliPath) : gettype($puliPath)
            ));
        }

        if ('' === $puliPath) {
            throw new \InvalidArgumentException('The Puli path must not be empty.');
        }

        $localPaths = (array) $localPaths;

        if (0 === count($localPaths)) {
            throw new \InvalidArgumentException('At least one local path must be passed.');
        }

        foreach ($localPaths as $localPath) {
            if (!is_string($localPath)) {
                throw new \InvalidArgumentException(sprintf(
                    'The local paths must be strings. Got: %s',
                    is_object($localPath) ? get_class($localPath) : gettype($localPath)
                ));
            }

            if ('' === $localPath) {
                throw new \InvalidArgumentException('The local paths must not be empty.');
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
