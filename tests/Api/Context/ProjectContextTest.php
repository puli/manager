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
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\Module\RootModuleFile;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectContextTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $config = new Config();
        $rootModuleFile = new RootModuleFile();
        $context = new ProjectContext(null, __DIR__, $config, $rootModuleFile);

        $this->assertNull($context->getHomeDirectory());
        $this->assertSame(Path::normalize(__DIR__), $context->getRootDirectory());
        $this->assertSame($config, $context->getConfig());
        $this->assertSame($rootModuleFile, $context->getRootModuleFile());
        $this->assertNull($context->getConfigFile());
        $this->assertSame(Environment::DEV, $context->getEnvironment());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
    }

    public function testCreateCanonicalizesRootDirectory()
    {
        $config = new Config();
        $rootModuleFile = new RootModuleFile();
        $context = new ProjectContext(null, __DIR__.'/../Context', $config, $rootModuleFile);

        $this->assertSame(Path::normalize(__DIR__), $context->getRootDirectory());
    }

    public function testCreateWithHomeDirectory()
    {
        $config = new Config();
        $rootModuleFile = new RootModuleFile();
        $context = new ProjectContext(__DIR__, __DIR__, $config, $rootModuleFile);

        $this->assertSame(Path::normalize(__DIR__), $context->getHomeDirectory());
    }

    public function testCreateWithConfigFile()
    {
        $config = new Config();
        $rootModuleFile = new RootModuleFile();
        $configFile = new ConfigFile();
        $context = new ProjectContext(null, __DIR__, $config, $rootModuleFile, $configFile);

        $this->assertSame($configFile, $context->getConfigFile());
    }

    public function testCreateWithDispatcher()
    {
        $config = new Config();
        $rootModuleFile = new RootModuleFile();
        $dispatcher = new EventDispatcher();
        $context = new ProjectContext(null, __DIR__, $config, $rootModuleFile, null, $dispatcher);

        $this->assertSame($dispatcher, $context->getEventDispatcher());
    }

    public function testCreateWithEnvironment()
    {
        $config = new Config();
        $rootModuleFile = new RootModuleFile();
        $context = new ProjectContext(null, __DIR__, $config, $rootModuleFile, null, null, Environment::PROD);

        $this->assertSame(Environment::PROD, $context->getEnvironment());
    }
}
