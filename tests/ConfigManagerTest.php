<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface;
use Puli\PackageManager\ConfigManager;
use Puli\PackageManager\Event\PackageConfigEvent;
use Puli\PackageManager\Event\PackageEvents;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigManager
     */
    private $manager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|GlobalConfigReaderInterface
     */
    private $globalConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|GlobalConfigWriterInterface
     */
    private $globalConfigWriter;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigWriterInterface
     */
    private $repositoryConfigWriter;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigReaderInterface
     */
    private $packageConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigWriterInterface
     */
    private $packageConfigWriter;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        $this->globalConfigReader = $this->getMock('Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface');
        $this->globalConfigWriter = $this->getMock('Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface');
        $this->repositoryConfigReader = $this->getMock('Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface');
        $this->repositoryConfigWriter = $this->getMock('Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface');
        $this->packageConfigReader = $this->getMock('Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface');
        $this->packageConfigWriter = $this->getMock('Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->manager = new ConfigManager(
            $this->globalConfigReader,
            $this->globalConfigWriter,
            $this->repositoryConfigReader,
            $this->repositoryConfigWriter,
            $this->packageConfigReader,
            $this->packageConfigWriter,
            $this->dispatcher
        );
    }

    public function testLoadGlobalConfig()
    {
        $config = new GlobalConfig();

        $this->globalConfigReader->expects($this->once())
            ->method('readGlobalConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->manager->loadGlobalConfig('/path'));
    }

    public function testLoadGlobalConfigCreatesNewIfNotFound()
    {
        $this->globalConfigReader->expects($this->once())
            ->method('readGlobalConfig')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->assertEquals(new GlobalConfig('/path'), $this->manager->loadGlobalConfig('/path'));
    }

    public function testSaveGlobalConfig()
    {
        $config = new GlobalConfig('/path');

        $this->globalConfigWriter->expects($this->once())
            ->method('writeGlobalConfig')
            ->with($config, '/path');

        $this->manager->saveGlobalConfig($config);
    }

    public function testLoadRepositoryConfig()
    {
        $config = new PackageRepositoryConfig();

        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->manager->loadRepositoryConfig('/path'));
    }

    public function testLoadRepositoryConfigCreatesNewIfNotFound()
    {
        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->assertEquals(new PackageRepositoryConfig('/path'), $this->manager->loadRepositoryConfig('/path'));
    }

    public function testSaveRepositoryConfig()
    {
        $config = new PackageRepositoryConfig('/path');

        $this->repositoryConfigWriter->expects($this->once())
            ->method('writeRepositoryConfig')
            ->with($config, '/path');

        $this->manager->saveRepositoryConfig($config);
    }

    public function testLoadPackageConfig()
    {
        $config = new PackageConfig('package-name');

        $this->packageConfigReader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->manager->loadPackageConfig('/path'));
    }

    /**
     * @expectedException \Puli\PackageManager\InvalidConfigException
     */
    public function testLoadPackageConfigFailsIfNoName()
    {
        $config = new PackageConfig();

        $this->packageConfigReader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->manager->loadPackageConfig('/path');
    }

    public function testLoadPackageConfigDispatchesEvent()
    {
        $config = new PackageConfig('package-name');

        $this->packageConfigReader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PackageEvents::LOAD_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PackageEvents::LOAD_PACKAGE_CONFIG, $this->isInstanceOf('Puli\PackageManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->assertSame($config, $this->manager->loadPackageConfig('/path'));
    }

    public function testLoadPackageConfigCreatesNewIfNotFoundAndNameSetByListener()
    {
        $this->packageConfigReader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PackageEvents::LOAD_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PackageEvents::LOAD_PACKAGE_CONFIG, $this->isInstanceOf('Puli\PackageManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) {
                $event->getPackageConfig()->setPackageName('package-name');
            }));

        $config = new PackageConfig('package-name', '/path');

        $this->assertEquals($config, $this->manager->loadPackageConfig('/path'));
    }

    public function testSavePackageConfig()
    {
        $config = new PackageConfig(null, '/path');

        $this->packageConfigWriter->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->manager->savePackageConfig($config);
    }

    public function testSavePackageConfigDispatchesEvent()
    {
        $config = new PackageConfig('package-name', '/path');

        $this->packageConfigWriter->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\PackageManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->manager->savePackageConfig($config);
    }

    public function testSavePackageConfigListenerMayRemoveName()
    {
        $config = new PackageConfig('package-name', '/path');

        $this->packageConfigWriter->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path')
            ->will($this->returnValue(function (PackageConfig $config) {
                \PHPUnit_Framework_Assert::assertNull($config->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\PackageManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) {
                $event->getPackageConfig()->setPackageName(null);
            }));

        $this->manager->savePackageConfig($config);
    }

    public function testLoadRootPackageConfig()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name');

        $this->packageConfigReader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->manager->loadRootPackageConfig('/path', $globalConfig));
    }

    public function testLoadRootPackageConfigDispatchesEvent()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name');

        $this->packageConfigReader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PackageEvents::LOAD_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PackageEvents::LOAD_PACKAGE_CONFIG, $this->isInstanceOf('Puli\PackageManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->assertSame($config, $this->manager->loadRootPackageConfig('/path', $globalConfig));
    }

    public function testSaveRootPackageConfig()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, null, '/path');

        $this->packageConfigWriter->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->manager->saveRootPackageConfig($config);
    }

    public function testSaveRootPackageConfigDispatchesEvent()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name', '/path');

        $this->packageConfigWriter->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\PackageManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->manager->saveRootPackageConfig($config);
    }

    public function testSaveRootPackageConfigListenerMayRemoveName()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name', '/path');

        $this->packageConfigWriter->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path')
            ->will($this->returnValue(function (RootPackageConfig $config) {
                \PHPUnit_Framework_Assert::assertNull($config->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PackageEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\PackageManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) {
                $event->getPackageConfig()->setPackageName(null);
            }));

        $this->manager->saveRootPackageConfig($config);
    }
}
