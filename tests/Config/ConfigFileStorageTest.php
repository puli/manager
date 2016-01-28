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
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Config\ConfigFileStorage;
use stdClass;
use Webmozart\Json\Conversion\ConversionFailedException;
use Webmozart\Json\Conversion\JsonConverter;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\EncodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;

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
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverter
     */
    private $configFileConverter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonEncoder
     */
    private $jsonEncoder;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonDecoder
     */
    private $jsonDecoder;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Storage
     */
    private $backend;

    protected function setUp()
    {
        $this->configFileConverter = $this->getMock('Webmozart\Json\Conversion\JsonConverter');
        $this->jsonEncoder = $this->getMockBuilder('Webmozart\Json\JsonEncoder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->jsonDecoder = $this->getMockBuilder('Webmozart\Json\JsonDecoder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->backend = $this->getMock('Puli\Manager\Api\Storage\Storage');

        $this->storage = new ConfigFileStorage(
            $this->backend,
            $this->configFileConverter,
            $this->jsonEncoder,
            $this->jsonDecoder
        );
    }

    public function testLoadConfigFile()
    {
        $baseConfig = new Config();
        $configFile = new ConfigFile('/path', $baseConfig);
        $jsonData = new stdClass();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willReturn($jsonData);

        $this->configFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path', 'baseConfig' => $baseConfig))
            ->will($this->returnValue($configFile));

        $this->assertSame($configFile, $this->storage->loadConfigFile('/path', $baseConfig));
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadConfigFileConvertsDecodingFailedException()
    {
        $baseConfig = new Config();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willThrowException(new DecodingFailedException());

        $this->configFileConverter->expects($this->never())
            ->method('fromJson');

        $this->storage->loadConfigFile('/path', $baseConfig);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadConfigFileConvertsConversionFailedException()
    {
        $baseConfig = new Config();
        $jsonData = new stdClass();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willReturn($jsonData);

        $this->configFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path', 'baseConfig' => $baseConfig))
            ->willThrowException(new ConversionFailedException());

        $this->storage->loadConfigFile('/path', $baseConfig);
    }

    public function testSaveConfigFile()
    {
        $baseConfig = new Config();
        $configFile = new ConfigFile('/path', $baseConfig);
        $jsonData = new stdClass();

        $this->configFileConverter->expects($this->once())
            ->method('toJson')
            ->with($configFile)
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willReturn('SERIALIZED');

        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->saveConfigFile($configFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveConfigFileConvertsEncodingFailedException()
    {
        $baseConfig = new Config();
        $configFile = new ConfigFile('/path', $baseConfig);
        $jsonData = new stdClass();

        $this->configFileConverter->expects($this->once())
            ->method('toJson')
            ->with($configFile)
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willThrowException(new EncodingFailedException());

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveConfigFile($configFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveConfigFileConvertsConversionFailedException()
    {
        $baseConfig = new Config();
        $configFile = new ConfigFile('/path', $baseConfig);

        $this->configFileConverter->expects($this->once())
            ->method('toJson')
            ->with($configFile)
            ->willThrowException(new ConversionFailedException());

        $this->jsonEncoder->expects($this->never())
            ->method('encode');

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveConfigFile($configFile);
    }

    public function testSaveConfigFileGeneratesFactoryIfManagerAvailable()
    {
        $factoryManager = $this->getMock('Puli\Manager\Api\Factory\FactoryManager');
        $baseConfig = new Config();
        $configFile = new ConfigFile('/path', $baseConfig);
        $jsonData = new stdClass();

        $this->storage = new ConfigFileStorage(
            $this->backend,
            $this->configFileConverter,
            $this->jsonEncoder,
            $this->jsonDecoder,
            $factoryManager
        );

        $this->configFileConverter->expects($this->once())
            ->method('toJson')
            ->with($configFile)
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willReturn('SERIALIZED');

        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $factoryManager->expects($this->once())
            ->method('autoGenerateFactoryClass');

        $this->storage->saveConfigFile($configFile);
    }
}
