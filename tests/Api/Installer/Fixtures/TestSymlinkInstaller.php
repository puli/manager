<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Installer\Fixtures;

use Puli\Manager\Api\Installer\ResourceInstaller;
use Puli\Manager\Api\Server\Server;
use Puli\Repository\Api\Resource\Resource;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestSymlinkInstaller implements ResourceInstaller
{
    public function installResource(Resource $resource, Server $server)
    {
    }
}
