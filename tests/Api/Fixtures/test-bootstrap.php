<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Fixtures;

use Puli\Manager\Api\Puli;
use Puli\Manager\Api\PuliPlugin;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BootstrapPlugin implements PuliPlugin
{
    public static $activated = false;

    public function activate(Puli $puli)
    {
        self::$activated = true;
    }
}

define('PULI_TEST_BOOTSTRAP_LOADED', true);
