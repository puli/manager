<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Repository\Iterator;

use Webmozart\Glob\Iterator\RecursiveDirectoryIterator;

/**
 * Recursively iterates over a filesystem path and its corresponding repository
 * path.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursivePathIterator extends RecursiveDirectoryIterator
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var int
     */
    private $flags;

    public function __construct($filesystemPath, $repositoryPath, $flags = null)
    {
        parent::__construct($filesystemPath, $flags);

        $this->repositoryPath = rtrim($repositoryPath, '/');
        $this->flags = $flags;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->repositoryPath.'/'.basename($this->key());
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren()
    {
        return new static($this->key(), $this->current(), $this->flags);
    }
}
