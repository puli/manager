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
class TestPlugin implements PuliPlugin
{
    /**
     * @var Puli
     */
    private static $puli;

    private static $environment;

    public static function reset()
    {
        self::$puli = null;
        self::$environment = null;
    }

    /**
     * @return Puli
     */
    public static function getPuli()
    {
        return self::$puli;
    }

    public static function getEnvironment()
    {
        return self::$environment;
    }

    public function activate(Puli $puli)
    {
        self::$puli = $puli;

        // Test that Puli is started and the services are accessible
        self::$environment = $puli->getEnvironment();
    }
}
