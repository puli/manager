<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Config\Fixtures;

use Puli\PackageManager\Manager\ProjectEnvironment;
use Puli\PackageManager\Plugin\PluginInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestPluginWithoutConstructor implements PluginInterface
{
    public function activate(ProjectEnvironment $environment)
    {
    }
}
