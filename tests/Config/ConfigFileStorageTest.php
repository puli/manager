<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Config;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Config\ConfigFileSerializer;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Config\ConfigFileStorage;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigFileStorage
     */
    private $storage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Storage
     */
    private $backend;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ConfigFileSerializer
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMock('Puli\Manager\Api\Config\ConfigFileSerializer');
        $this->backend = $this->getMock('Puli\Manager\Api\Storage\Storage');

        $this->storage = new ConfigFileStorage($this->backend, $this->serializer);
    }

    public function testLoadConfigFile()
    {
        $configFile = new ConfigFile();

        $this->backend->expects($this->once())
            ->method('exists')
            ->with('/path')
            ->willReturn(true);
        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');
        $this->serializer->expects($this->once())
            ->method('unserializeConfigFile')
            ->with('SERIALIZED', '/path')
            ->willReturn($configFile);

        $this->assertSame($configFile, $this->storage->loadConfigFile('/path'));
    }

    public function testLoadConfigFileWithBaseConfig()
    {
        $baseConfig = new Config();
        $configFile = new ConfigFile();

        $this->backend->expects($this->once())
            ->method('exists')
            ->with('/path')
            ->willReturn(true);
        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');
        $this->serializer->expects($this->once())
            ->method('unserializeConfigFile')
            ->with('SERIALIZED', '/path', $baseConfig)
            ->willReturn($configFile);

        $this->assertSame($configFile, $this->storage->loadConfigFile('/path', $baseConfig));
    }

    public function testLoadConfigFileCreatesNewIfNotFound()
    {
        $baseConfig = new Config();
        $configFile = new ConfigFile('/path', $baseConfig);

        $this->backend->expects($this->once())
            ->method('exists')
            ->with('/path')
            ->willReturn(false);
        $this->backend->expects($this->never())
            ->method('read');
        $this->serializer->expects($this->never())
            ->method('unserializeConfigFile');

        $this->assertEquals($configFile, $this->storage->loadConfigFile('/path', $baseConfig));
    }

    public function testSaveConfigFile()
    {
        $configFile = new ConfigFile('/path');

        $this->serializer->expects($this->once())
            ->method('serializeConfigFile')
            ->with($configFile)
            ->willReturn('SERIALIZED');
        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->saveConfigFile($configFile);
    }

    public function testSaveConfigFileGeneratesFactoryIfManagerAvailable()
    {
        $configFile = new ConfigFile('/path');

        $factoryManager = $this->getMock('Puli\Manager\Api\Factory\FactoryManager');
        $storage = new ConfigFileStorage($this->backend, $this->serializer, $factoryManager);

        $factoryManager->expects($this->once())
            ->method('autoGenerateFactoryClass');

        $storage->saveConfigFile($configFile);
    }
}
