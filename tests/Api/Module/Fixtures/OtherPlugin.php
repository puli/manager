<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Module\Fixtures;

use Puli\Manager\Api\Container;
use Puli\Manager\Api\PuliPlugin;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OtherPlugin implements PuliPlugin
{
    public function activate(Container $container)
    {
    }
}
