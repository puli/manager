<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\Config;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Event\PackageConfigEvent;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\ManagerEvents;
use Puli\RepositoryManager\Package\Config\PackageConfig;
use Puli\RepositoryManager\Package\Config\PackageConfigStorage;
use Puli\RepositoryManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Package\Config\Writer\PackageConfigWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConfigStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageConfigStorage
     */
    private $storage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigReaderInterface
     */
    private $reader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigWriterInterface
     */
    private $writer;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        $this->reader = $this->getMock('Puli\RepositoryManager\Package\Config\Reader\PackageConfigReaderInterface');
        $this->writer = $this->getMock('Puli\RepositoryManager\Package\Config\Writer\PackageConfigWriterInterface');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->storage = new PackageConfigStorage($this->reader, $this->writer, $this->dispatcher);
    }

    public function testLoadPackageConfig()
    {
        $config = new PackageConfig('package-name');

        $this->reader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->storage->loadPackageConfig('/path'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testLoadPackageConfigFailsIfNoName()
    {
        $config = new PackageConfig();

        $this->reader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->storage->loadPackageConfig('/path');
    }

    public function testLoadPackageConfigDispatchesEvent()
    {
        $config = new PackageConfig('package-name');

        $this->reader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::LOAD_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::LOAD_PACKAGE_CONFIG, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->assertSame($config, $this->storage->loadPackageConfig('/path'));
    }

    public function testLoadPackageConfigCreatesNewIfNotFoundAndNameSetByListener()
    {
        $this->reader->expects($this->once())
            ->method('readPackageConfig')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::LOAD_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::LOAD_PACKAGE_CONFIG, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) {
                $event->getPackageConfig()->setPackageName('package-name');
            }));

        $config = new PackageConfig('package-name', '/path');

        $this->assertEquals($config, $this->storage->loadPackageConfig('/path'));
    }

    public function testSavePackageConfig()
    {
        $config = new PackageConfig(null, '/path');

        $this->writer->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->storage->savePackageConfig($config);
    }

    public function testSavePackageConfigDispatchesEvent()
    {
        $config = new PackageConfig('package-name', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->storage->savePackageConfig($config);
    }

    public function testSavePackageConfigListenerMayRemoveName()
    {
        $config = new PackageConfig('package-name', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path')
            ->will($this->returnValue(function (PackageConfig $config) {
                \PHPUnit_Framework_Assert::assertNull($config->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) {
                $event->getPackageConfig()->setPackageName(null);
            }));

        $this->storage->savePackageConfig($config);
    }

    public function testLoadRootPackageConfig()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name');

        $this->reader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->storage->loadRootPackageConfig('/path', $globalConfig));
    }

    public function testLoadRootPackageConfigDispatchesEvent()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name');

        $this->reader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::LOAD_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::LOAD_PACKAGE_CONFIG, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->assertSame($config, $this->storage->loadRootPackageConfig('/path', $globalConfig));
    }

    public function testSaveRootPackageConfig()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, null, '/path');

        $this->writer->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->storage->saveRootPackageConfig($config);
    }

    public function testSaveRootPackageConfigDispatchesEvent()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) use ($config) {
                \PHPUnit_Framework_Assert::assertSame($config, $event->getPackageConfig());
            }));

        $this->storage->saveRootPackageConfig($config);
    }

    public function testSaveRootPackageConfigListenerMayRemoveName()
    {
        $globalConfig = new GlobalConfig();
        $config = new RootPackageConfig($globalConfig, 'package-name', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageConfig')
            ->with($config, '/path')
            ->will($this->returnValue(function (RootPackageConfig $config) {
                \PHPUnit_Framework_Assert::assertNull($config->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_CONFIG, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageConfigEvent'))
            ->will($this->returnCallback(function ($eventName, PackageConfigEvent $event) {
                $event->getPackageConfig()->setPackageName(null);
            }));

        $this->storage->saveRootPackageConfig($config);
    }
}
