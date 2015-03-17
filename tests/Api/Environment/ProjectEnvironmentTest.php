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
use Puli\Manager\Api\Environment\ProjectEnvironment;
use Puli\Manager\Api\Package\RootPackageFile;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironmentTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $environment = new ProjectEnvironment(null, __DIR__, $config, $rootPackageFile);

        $this->assertNull($environment->getHomeDirectory());
        $this->assertSame(__DIR__, $environment->getRootDirectory());
        $this->assertSame($config, $environment->getConfig());
        $this->assertSame($rootPackageFile, $environment->getRootPackageFile());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
    }

    public function testCreateCanonicalizesRootDirectory()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $environment = new ProjectEnvironment(null, __DIR__.'/../Environment', $config, $rootPackageFile);

        $this->assertSame(__DIR__, $environment->getRootDirectory());
    }

    public function testCreateWithHomeDirectory()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $environment = new ProjectEnvironment(__DIR__, __DIR__, $config, $rootPackageFile);

        $this->assertSame(__DIR__, $environment->getHomeDirectory());
    }

    public function testCreateWithConfigFile()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $configFile = new ConfigFile();
        $environment = new ProjectEnvironment(null, __DIR__, $config, $rootPackageFile, $configFile);

        $this->assertSame($configFile, $environment->getConfigFile());
    }

    public function testCreateWithDispatcher()
    {
        $config = new Config();
        $rootPackageFile = new RootPackageFile();
        $dispatcher = new EventDispatcher();
        $environment = new ProjectEnvironment(null, __DIR__, $config, $rootPackageFile, null, $dispatcher);

        $this->assertSame($dispatcher, $environment->getEventDispatcher());
    }
}
