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

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestDiscovery
{
    private $repo;

    public function __construct(TestRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getRepository()
    {
        return $this->repo;
    }
}
