<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Package;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileSerializer;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Package\PackageFileStorage;

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
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileSerializer
     */
    private $serializer;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Storage
     */
    private $backend;

    protected function setUp()
    {
        $this->serializer = $this->getMock('Puli\Manager\Api\Package\PackageFileSerializer');
        $this->backend = $this->getMock('Puli\Manager\Api\Storage\Storage');

        $this->storage = new PackageFileStorage($this->backend, $this->serializer);
    }

    public function testLoadPackageFile()
    {
        $packageFile = new PackageFile(null);

        $this->backend->expects($this->once())
            ->method('exists')
            ->with('/path')
            ->willReturn(true);
        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');
        $this->serializer->expects($this->once())
            ->method('unserializePackageFile')
            ->with('SERIALIZED', '/path')
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadPackageFile('/path'));
    }

    public function testLoadPackageFileReturnsNewIfNotFound()
    {
        $packageFile = new PackageFile(null, '/path');

        $this->backend->expects($this->once())
            ->method('exists')
            ->with('/path')
            ->willReturn(false);
        $this->backend->expects($this->never())
            ->method('read');
        $this->serializer->expects($this->never())
            ->method('unserializePackageFile');

        $this->assertEquals($packageFile, $this->storage->loadPackageFile('/path'));
    }

    public function testSavePackageFile()
    {
        $packageFile = new PackageFile(null, '/path');

        $this->serializer->expects($this->once())
            ->method('serializePackageFile')
            ->with($packageFile)
            ->willReturn('SERIALIZED');
        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->savePackageFile($packageFile);
    }

    public function testLoadRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);

        $this->backend->expects($this->once())
            ->method('exists')
            ->with('/path')
            ->willReturn(true);
        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');
        $this->serializer->expects($this->once())
            ->method('unserializeRootPackageFile')
            ->with('SERIALIZED', '/path', $baseConfig)
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadRootPackageFile('/path', $baseConfig));
    }

    public function testLoadRootPackageFileCreatesNewIfNotFound()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);

        $this->backend->expects($this->once())
            ->method('exists')
            ->with('/path')
            ->willReturn(false);
        $this->backend->expects($this->never())
            ->method('read');
        $this->serializer->expects($this->never())
            ->method('unserializeRootPackageFile');

        $this->assertEquals($packageFile, $this->storage->loadRootPackageFile('/path', $baseConfig));
    }

    public function testSaveRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);

        $this->serializer->expects($this->once())
            ->method('serializeRootPackageFile')
            ->with($packageFile)
            ->willReturn('SERIALIZED');
        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->saveRootPackageFile($packageFile);
    }

    public function testSaveRootPackageFileGeneratesFactoryIfManagerAvailable()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);

        $factoryManager = $this->getMock('Puli\Manager\Api\Factory\FactoryManager');
        $storage = new PackageFileStorage($this->backend, $this->serializer, $factoryManager);

        $factoryManager->expects($this->once())
            ->method('autoGenerateFactoryClass');

        $storage->saveRootPackageFile($packageFile);
    }
}
