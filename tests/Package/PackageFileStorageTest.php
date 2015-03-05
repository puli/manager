<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Event\PackageFileEvent;
use Puli\RepositoryManager\Api\Event\PuliEvents;
use Puli\RepositoryManager\Api\FileNotFoundException;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\PackageFileReader;
use Puli\RepositoryManager\Api\Package\PackageFileWriter;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PackageFileStorage
     */
    private $storage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileReader
     */
    private $reader;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileWriter
     */
    private $writer;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        $this->reader = $this->getMock('Puli\RepositoryManager\Api\Package\PackageFileReader');
        $this->writer = $this->getMock('Puli\RepositoryManager\Api\Package\PackageFileWriter');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->storage = new PackageFileStorage($this->reader, $this->writer, $this->dispatcher);
    }

    public function testLoadPackageFile()
    {
        $packageFile = new PackageFile('vendor/package');

        $this->reader->expects($this->once())
            ->method('readPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadPackageFile('/path'));
    }

    public function testLoadPackageFileDispatchesEvent()
    {
        $packageFile = new PackageFile('vendor/package');

        $this->reader->expects($this->once())
            ->method('readPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::LOAD_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::LOAD_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Api\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
            }));

        $this->assertSame($packageFile, $this->storage->loadPackageFile('/path'));
    }

    public function testLoadPackageFileCreatesNewIfNotFoundAndNameSetByListener()
    {
        $this->reader->expects($this->once())
            ->method('readPackageFile')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::LOAD_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::LOAD_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Api\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) {
                $event->getPackageFile()->setPackageName('vendor/package');
            }));

        $packageFile = new PackageFile('vendor/package', '/path');

        $this->assertEquals($packageFile, $this->storage->loadPackageFile('/path'));
    }

    public function testSavePackageFile()
    {
        $packageFile = new PackageFile(null, '/path');

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

        $this->storage->savePackageFile($packageFile);
    }

    public function testSavePackageFileDispatchesEvent()
    {
        $packageFile = new PackageFile('vendor/package', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Api\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
            }));

        $this->storage->savePackageFile($packageFile);
    }

    public function testSavePackageFileListenerMayRemoveName()
    {
        $packageFile = new PackageFile('vendor/package', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path')
            ->will($this->returnValue(function (PackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertNull($packageFile->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Api\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) {
                $event->getPackageFile()->setPackageName(null);
            }));

        $this->storage->savePackageFile($packageFile);
    }

    public function testLoadRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile('vendor/package', null, $baseConfig);

        $this->reader->expects($this->once())
            ->method('readRootPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadRootPackageFile('/path', $baseConfig));
    }

    public function testLoadRootPackageFileDispatchesEvent()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile('vendor/package', null, $baseConfig);

        $this->reader->expects($this->once())
            ->method('readRootPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::LOAD_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::LOAD_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Api\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
            }));

        $this->assertSame($packageFile, $this->storage->loadRootPackageFile('/path', $baseConfig));
    }

    public function testSaveRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

        $this->storage->saveRootPackageFile($packageFile);
    }

    public function testSaveRootPackageFileDispatchesEvent()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile('vendor/package', '/path', $baseConfig);

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Api\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
            }));

        $this->storage->saveRootPackageFile($packageFile);
    }

    public function testSaveRootPackageFileListenerMayRemoveName()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile('vendor/package', '/path', $baseConfig);

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path')
            ->will($this->returnValue(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertNull($packageFile->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Api\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) {
                $event->getPackageFile()->setPackageName(null);
            }));

        $this->storage->saveRootPackageFile($packageFile);
    }
}
