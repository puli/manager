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

use Puli\Manager\Api\Puli;
use Puli\Manager\Api\PuliPlugin;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TestPlugin implements PuliPlugin
{
    /**
     * @var Puli
     */
    private static $puli;

    private static $context;

    public static function reset()
    {
        self::$puli = null;
        self::$context = null;
    }

    /**
     * @return Puli
     */
    public static function getPuli()
    {
        return self::$puli;
    }

    public static function getContext()
    {
        return self::$context;
    }

    public function activate(Puli $puli)
    {
        self::$puli = $puli;

        // Test that Puli is started and the services are accessible
        self::$context = $puli->getContext();
    }
}
