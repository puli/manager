<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Package\InstallFile;

use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\Package\InstallFile\InstallFile;
use Puli\PackageManager\Package\InstallFile\InstallFileStorage;
use Puli\PackageManager\Package\InstallFile\Reader\InstallFileReaderInterface;
use Puli\PackageManager\Package\InstallFile\Writer\InstallFileWriterInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallFileStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InstallFileStorage
     */
    private $storage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|InstallFileReaderInterface
     */
    private $reader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|InstallFileWriterInterface
     */
    private $writer;

    protected function setUp()
    {
        $this->reader = $this->getMock('Puli\PackageManager\Package\InstallFile\Reader\InstallFileReaderInterface');
        $this->writer = $this->getMock('Puli\PackageManager\Package\InstallFile\Writer\InstallFileWriterInterface');

        $this->storage = new InstallFileStorage($this->reader, $this->writer);
    }

    public function testLoadInstallFile()
    {
        $config = new InstallFile();

        $this->reader->expects($this->once())
            ->method('readInstallFile')
            ->with('/path')
            ->will($this->returnValue($config));

        $this->assertSame($config, $this->storage->loadInstallFile('/path'));
    }

    public function testLoadInstallFileCreatesNewIfNotFound()
    {
        $this->reader->expects($this->once())
            ->method('readInstallFile')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->assertEquals(new InstallFile('/path'), $this->storage->loadInstallFile('/path'));
    }

    public function testSaveInstallFile()
    {
        $config = new InstallFile('/path');

        $this->writer->expects($this->once())
            ->method('writeInstallFile')
            ->with($config, '/path');

        $this->storage->saveInstallFile($config);
    }
}
