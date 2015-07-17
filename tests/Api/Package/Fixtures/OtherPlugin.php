<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Package\Fixtures;

use Puli\Manager\Api\Puli;
use Puli\Manager\Api\PuliPlugin;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OtherPlugin implements PuliPlugin
{
    public function activate(Puli $puli)
    {
    }
}
