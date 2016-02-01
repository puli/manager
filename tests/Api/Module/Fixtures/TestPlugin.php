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
class TestPlugin implements PuliPlugin
{
    /**
     * @var Container
     */
    private static $container;

    private static $context;

    public static function reset()
    {
        self::$container = null;
        self::$context = null;
    }

    /**
     * @return Container
     */
    public static function getContainer()
    {
        return self::$container;
    }

    public static function getContext()
    {
        return self::$context;
    }

    public function activate(Container $container)
    {
        self::$container = $container;

        // Test that Puli is started and the services are accessible
        self::$context = $container->getContext();
    }
}
