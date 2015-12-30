<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\Fixtures;

use Puli\Repository\Api\ResourceRepository;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestRepository implements ResourceRepository
{
    private $path;

    public function __construct($path = null)
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function get($path, $version = null)
    {
    }

    public function find($query, $language = 'glob')
    {
    }

    public function contains($query, $language = 'glob')
    {
    }

    public function hasChildren($path)
    {
    }

    public function listChildren($path)
    {
    }

    public function getStack($path)
    {
    }
}
