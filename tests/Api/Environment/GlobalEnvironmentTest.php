<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Environment;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Environment\GlobalEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalEnvironmentTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $config = new Config();
        $environment = new GlobalEnvironment(null, $config);

        $this->assertNull($environment->getHomeDirectory());
        $this->assertSame($config, $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
    }

    public function testCreateWithHomeDirectory()
    {
        $config = new Config();
        $environment = new GlobalEnvironment(__DIR__, $config);

        $this->assertSame(__DIR__, $environment->getHomeDirectory());
    }

    public function testCreateCanonicalizesHomeDirectory()
    {
        $config = new Config();
        $environment = new GlobalEnvironment(__DIR__.'/../Environment', $config);

        $this->assertSame(__DIR__, $environment->getHomeDirectory());
    }

    public function testCreateWithConfigFile()
    {
        $config = new Config();
        $configFile = new ConfigFile();
        $environment = new GlobalEnvironment(null, $config, $configFile);

        $this->assertSame($configFile, $environment->getConfigFile());
    }

    public function testCreateWithDispatcher()
    {
        $config = new Config();
        $dispatcher = new EventDispatcher();
        $environment = new GlobalEnvironment(null, $config, null, $dispatcher);

        $this->assertSame($dispatcher, $environment->getEventDispatcher());
    }
}
