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

    protected function setUp()
    {
        $this->reader = $this->getMock('Puli\RepositoryManager\Api\Package\PackageFileReader');
        $this->writer = $this->getMock('Puli\RepositoryManager\Api\Package\PackageFileWriter');

        $this->storage = new PackageFileStorage($this->reader, $this->writer);
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

    public function testSavePackageFile()
    {
        $packageFile = new PackageFile(null, '/path');

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

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

    public function testSaveRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);

        $this->writer->expects($this->once())
            ->method('writePackageFile')
            ->with($packageFile, '/path');

        $this->storage->saveRootPackageFile($packageFile);
    }
}
