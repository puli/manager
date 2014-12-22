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

use Puli\Discovery\ResourceDiscovery;
use Puli\Discovery\Storage\DiscoveryStorage;
use Puli\Repository\ResourceRepository;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MyDiscoveryStorage implements DiscoveryStorage
{
    public function storeDiscovery(ResourceDiscovery $discovery, array $options = array())
    {
    }

    public function loadDiscovery(ResourceRepository $repo, array $options = array())
    {
    }
}
