<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator\Fixtures;

use Puli\Repository\ResourceRepository;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestRepository implements ResourceRepository
{
    private $storageDir;

    public function __construct($storageDir = null)
    {
        $this->storageDir = $storageDir;
    }

    public function getStorageDir()
    {
        return $this->storageDir;
    }

    public function get($path, $version = null)
    {
    }

    public function find($selector)
    {
    }

    public function contains($selector)
    {
    }

    public function listDirectory($path)
    {
    }
}
