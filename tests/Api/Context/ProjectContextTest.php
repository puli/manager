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
use Puli\Manager\Api\Package\RootPackageFile;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
        $rootPackageFile = new RootPackageFile();
        $context = new ProjectContext(null, __DIR__, $config, $rootPackageFile);

        $this->assertNull($context->getHomeDirectory());
        $this->assertSame(__DIR__, $context->getRootDirectory());
        $this->assertSame($config, $context->getConfig());
        $this->assertSame($rootPackageFile, $context->getRootPackageFile());
        $this->assertNull($context->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
    }

    public function testCreateCanonicalizesRootDirectory()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $context = new ProjectContext(null, __DIR__.'/../Context', $config, $rootPackageFile);

        $this->assertSame(__DIR__, $context->getRootDirectory());
    }

    public function testCreateWithHomeDirectory()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $context = new ProjectContext(__DIR__, __DIR__, $config, $rootPackageFile);

        $this->assertSame(__DIR__, $context->getHomeDirectory());
    }

    public function testCreateWithConfigFile()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $configFile = new ConfigFile();
        $context = new ProjectContext(null, __DIR__, $config, $rootPackageFile, $configFile);

        $this->assertSame($configFile, $context->getConfigFile());
    }

    public function testCreateWithDispatcher()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $dispatcher = new EventDispatcher();
        $context = new ProjectContext(null, __DIR__, $config, $rootPackageFile, null, $dispatcher);

        $this->assertSame($dispatcher, $context->getEventDispatcher());
    }
}
