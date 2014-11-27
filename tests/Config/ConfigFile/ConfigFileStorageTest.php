<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Config\ConfigFile;

use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigFileReaderInterface;
use Puli\RepositoryManager\Config\ConfigFile\Writer\ConfigFileWriterInterface;
use Puli\RepositoryManager\FileNotFoundException;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigFileStorage
     */
    private $storage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ConfigFileReaderInterface
     */
    private $reader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ConfigFileWriterInterface
     */
    private $writer;

    protected function setUp()
    {
        $this->reader = $this->getMock('Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigFileReaderInterface');
        $this->writer = $this->getMock('Puli\RepositoryManager\Config\ConfigFile\Writer\ConfigFileWriterInterface');

        $this->storage = new ConfigFileStorage($this->reader, $this->writer);
    }

    public function testLoadConfigFile()
    {
        $configFile = new ConfigFile();

        $this->reader->expects($this->once())
            ->method('readConfigFile')
            ->with('/path')
            ->will($this->returnValue($configFile));

        $this->assertSame($configFile, $this->storage->loadConfigFile('/path'));
    }

    public function testLoadConfigFileCreatesNewIfNotFound()
    {
        $this->reader->expects($this->once())
            ->method('readConfigFile')
            ->with('/path')
            ->will($this->throwException(new FileNotFoundException()));

        $this->assertEquals(new ConfigFile('/path'), $this->storage->loadConfigFile('/path'));
    }

    public function testSaveConfigFile()
    {
        $configFile = new ConfigFile('/path');

        $this->writer->expects($this->once())
            ->method('writeConfigFile')
            ->with($configFile, '/path');

        $this->storage->saveConfigFile($configFile);
    }
}
