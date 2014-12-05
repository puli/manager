<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Event\PackageFileEvent;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\ManagerEvents;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\Reader\PackageFileReaderInterface;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\PackageFile\Writer\PackageFileWriterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PackageFileStorage
     */
    private $storage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageFileReaderInterface
     */
    private $reader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageFileWriterInterface
     */
    private $writer;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        $this->reader = $this->getMock('Puli\RepositoryManager\Package\PackageFile\Reader\PackageFileReaderInterface');
        $this->writer = $this->getMock('Puli\RepositoryManager\Package\PackageFile\Writer\PackageFileWriterInterface');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->storage = new PackageFileStorage($this->reader, $this->writer, $this->dispatcher);
    }

    public function testLoadPackageFile()
    {
        $packageFile = new PackageFile('package-name');

        $this->reader->expects($this->once())
            ->method('readPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadPackageFile('/path'));
    }

    public function testLoadPackageFileDispatchesEvent()
    {
        $packageFile = new PackageFile('package-name');

        $this->reader->expects($this->once())
            ->method('readPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::LOAD_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::LOAD_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                \PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
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
            ->with(ManagerEvents::LOAD_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::LOAD_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) {
                $event->getPackageFile()->setPackageName('package-name');
            }));

        $packageFile = new PackageFile('package-name', '/path');

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
        $packageFile = new PackageFile('package-name', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                \PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
            }));

        $this->storage->savePackageFile($packageFile);
    }

    public function testSavePackageFileListenerMayRemoveName()
    {
        $packageFile = new PackageFile('package-name', '/path');

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path')
            ->will($this->returnValue(function (PackageFile $packageFile) {
                \PHPUnit_Framework_Assert::assertNull($packageFile->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) {
                $event->getPackageFile()->setPackageName(null);
            }));

        $this->storage->savePackageFile($packageFile);
    }

    public function testLoadRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile('package-name', null, $baseConfig);

        $this->reader->expects($this->once())
            ->method('readRootPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadRootPackageFile('/path', $baseConfig));
    }

    public function testLoadRootPackageFileDispatchesEvent()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile('package-name', null, $baseConfig);

        $this->reader->expects($this->once())
            ->method('readRootPackageFile')
            ->with('/path')
            ->will($this->returnValue($packageFile));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::LOAD_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::LOAD_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                \PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
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
        $packageFile = new RootPackageFile('package-name', '/path',
            $baseConfig);

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) use ($packageFile) {
                \PHPUnit_Framework_Assert::assertSame($packageFile, $event->getPackageFile());
            }));

        $this->storage->saveRootPackageFile($packageFile);
    }

    public function testSaveRootPackageFileListenerMayRemoveName()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile('package-name', '/path',
            $baseConfig);

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path')
            ->will($this->returnValue(function (RootPackageFile $packageFile) {
                \PHPUnit_Framework_Assert::assertNull($packageFile->getPackageName());
            }));

        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE)
            ->will($this->returnValue(true));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(ManagerEvents::SAVE_PACKAGE_FILE, $this->isInstanceOf('Puli\RepositoryManager\Event\PackageFileEvent'))
            ->will($this->returnCallback(function ($eventName, PackageFileEvent $event) {
                $event->getPackageFile()->setPackageName(null);
            }));

        $this->storage->saveRootPackageFile($packageFile);
    }
}
