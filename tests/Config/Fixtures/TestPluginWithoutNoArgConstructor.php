<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Config\Fixtures;

use Puli\RepositoryManager\Plugin\PluginInterface;
use Puli\RepositoryManager\Project\ProjectEnvironment;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestPluginWithoutNoArgConstructor implements PluginInterface
{
    public function __construct($arg)
    {
    }

    public function activate(ProjectEnvironment $environment)
    {
    }
}
