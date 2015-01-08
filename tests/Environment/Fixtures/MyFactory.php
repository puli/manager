<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Environment\Fixtures;

use Puli\Factory\PuliFactory;
use Puli\Repository\Api\ResourceRepository;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MyFactory implements PuliFactory
{
    public function createRepository()
    {
    }

    public function createDiscovery(ResourceRepository $repo)
    {
    }
}
