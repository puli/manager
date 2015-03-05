<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Api\Package\Fixtures;

use Puli\RepositoryManager\Api\Puli;
use Puli\RepositoryManager\Api\PuliPlugin;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OtherPlugin implements PuliPlugin
{
    public function activate(Puli $puli)
    {
    }
}
