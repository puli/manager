<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository;

use InvalidArgumentException;
use Puli\Repository\Assert\Assertion;

/**
 * Maps a repository path to one or more filesystem paths.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResourceMapping
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var string[]
     */
    private $filesystemPaths = array();

    /**
     * Creates a new resource mapping.
     *
     * The mapping maps a repository path to one or more filesystem paths
     * relative to the root of the package.
     *
     * @param string          $repositoryPath  The repository path.
     * @param string|string[] $filesystemPaths The filesystem paths.
     *
     * @throws InvalidArgumentException If any of the arguments is invalid.
     */
    public function __construct($repositoryPath, $filesystemPaths)
    {
        Assertion::path($repositoryPath);

        $filesystemPaths = (array) $filesystemPaths;

        Assertion::notEmpty($filesystemPaths, 'At least one local path must be passed.');
        Assertion::allString($filesystemPaths, 'The local paths must be strings. Got: %2$s');
        Assertion::allNotEmpty($filesystemPaths, 'The local paths must not be empty.');

        $this->repositoryPath = $repositoryPath;
        $this->filesystemPaths = $filesystemPaths;
    }

    /**
     * Returns the repository path.
     *
     * @return string The repository path.
     */
    public function getRepositoryPath()
    {
        return $this->repositoryPath;
    }

    /**
     * Returns the filesystem paths that the repository path is mapped to.
     *
     * The paths are either absolute or relative to the root of the Puli package.
     *
     * @return string[] The filesystem paths.
     */
    public function getFilesystemPaths()
    {
        return $this->filesystemPaths;
    }
}
