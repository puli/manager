<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Context;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Context\Context;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ContextTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $config = new Config();
        $context = new Context(null, $config);

        $this->assertNull($context->getHomeDirectory());
        $this->assertSame($config, $context->getConfig());
        $this->assertNull($context->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
    }

    public function testCreateWithHomeDirectory()
    {
        $config = new Config();
        $context = new Context(__DIR__, $config);

        $this->assertSame(__DIR__, $context->getHomeDirectory());
    }

    public function testCreateCanonicalizesHomeDirectory()
    {
        $config = new Config();
        $context = new Context(__DIR__.'/../Context', $config);

        $this->assertSame(__DIR__, $context->getHomeDirectory());
    }

    public function testCreateWithConfigFile()
    {
        $config = new Config();
        $configFile = new ConfigFile();
        $context = new Context(null, $config, $configFile);

        $this->assertSame($configFile, $context->getConfigFile());
    }

    public function testCreateWithDispatcher()
    {
        $config = new Config();
        $dispatcher = new EventDispatcher();
        $context = new Context(null, $config, null, $dispatcher);

        $this->assertSame($dispatcher, $context->getEventDispatcher());
    }
}
