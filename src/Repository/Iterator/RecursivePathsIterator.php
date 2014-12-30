<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Repository\Iterator;

use Iterator;
use RecursiveIterator;
use RuntimeException;

/**
 * Recursively iterates over a list of of filesystem paths and their
 * corresponding repository paths.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursivePathsIterator implements RecursiveIterator
{
    /**
     * Flag: Return current value as file path.
     */
    const CURRENT_AS_PATH = 1;

    /**
     * Flag: Return current value as file name.
     */
    const CURRENT_AS_FILE = 2;

    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @var Iterator
     */
    private $filesystemPaths;

    /**
     * @var bool
     */
    private $failed;

    public function __construct(Iterator $filesystemPaths, $repositoryPath)
    {
        $this->filesystemPaths = $filesystemPaths;
        $this->repositoryPath = $repositoryPath;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->repositoryPath;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->filesystemPaths->current();
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        if (!$this->valid()) {
            return;
        }

        $this->filesystemPaths->next();

        $this->validate();
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return !$this->failed && $this->filesystemPaths->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->filesystemPaths->rewind();

        $this->failed = false;

        $this->validate();
    }

    /**
     * {@inheritdoc}
     */
    public function hasChildren()
    {
        if (!$this->valid()) {
            return false;
        }

        return is_dir($this->filesystemPaths->current());
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren()
    {
        return new RecursivePathIterator($this->key(), $this->repositoryPath);
    }

    private function validate()
    {
        if (!$this->filesystemPaths->valid()) {
            return;
        }

        if (!file_exists($path = $this->filesystemPaths->current())) {
            $this->failed = true;

            throw new RuntimeException(sprintf(
                'The path "%s" was expected to be a file.',
                $path
            ));
        }
    }
}
