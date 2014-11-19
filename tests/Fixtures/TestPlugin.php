<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Fixtures;

use Puli\PackageManager\PackageManager;
use Puli\PackageManager\Plugin\PluginInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestPlugin implements PluginInterface
{
    /**
     * @var bool
     */
    private static $dispatcher = false;

    /**
     * @var PackageManager
     */
    private static $manager;

    /**
     * @return PackageManager
     */
    public static function getManager()
    {
        return self::$manager;
    }

    /**
     * @return bool
     */
    public static function getDispatcher()
    {
        return self::$dispatcher;
    }

    public function activate(PackageManager $manager, EventDispatcherInterface $dispatcher)
    {
        self::$manager = $manager;
        self::$dispatcher = $dispatcher;
    }
}
