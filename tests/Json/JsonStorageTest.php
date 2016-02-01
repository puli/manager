<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Json;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Json\JsonConverterProvider;
use Puli\Manager\Json\JsonStorage;
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
class JsonStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var JsonStorage
     */
    private $storage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverter
     */
    private $configFileConverter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverter
     */
    private $moduleFileConverter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverter
     */
    private $rootModuleFileConverter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverterProvider
     */
    private $converterProvider;

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
        $this->moduleFileConverter = $this->getMock('Webmozart\Json\Conversion\JsonConverter');
        $this->rootModuleFileConverter = $this->getMock('Webmozart\Json\Conversion\JsonConverter');
        $this->converterProvider = $this->getMockBuilder('Puli\Manager\Json\JsonConverterProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->jsonEncoder = $this->getMockBuilder('Webmozart\Json\JsonEncoder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->jsonDecoder = $this->getMockBuilder('Webmozart\Json\JsonDecoder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->backend = $this->getMock('Puli\Manager\Api\Storage\Storage');

        $this->converterProvider->expects($this->any())
            ->method('getJsonConverter')
            ->willReturnMap(array(
                array('Puli\Manager\Api\Config\ConfigFile', $this->configFileConverter),
                array('Puli\Manager\Api\Module\ModuleFile', $this->moduleFileConverter),
                array('Puli\Manager\Api\Module\RootModuleFile', $this->rootModuleFileConverter),
            ));

        $this->storage = new JsonStorage(
            $this->backend,
            $this->converterProvider,
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

        $this->storage = new JsonStorage(
            $this->backend,
            $this->converterProvider,
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

    public function testLoadModuleFile()
    {
        $moduleFile = new ModuleFile(null);
        $jsonData = new stdClass();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willReturn($jsonData);

        $this->moduleFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path'))
            ->will($this->returnValue($moduleFile));

        $this->assertSame($moduleFile, $this->storage->loadModuleFile('/path'));
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadModuleFileConvertsDecodingFailedException()
    {
        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willThrowException(new DecodingFailedException());

        $this->moduleFileConverter->expects($this->never())
            ->method('fromJson');

        $this->storage->loadModuleFile('/path');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadModuleFileConvertsConversionFailedException()
    {
        $jsonData = new stdClass();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willReturn($jsonData);

        $this->moduleFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path'))
            ->willThrowException(new ConversionFailedException());

        $this->storage->loadModuleFile('/path');
    }

    public function testSaveModuleFile()
    {
        $moduleFile = new ModuleFile(null, '/path');
        $moduleFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->moduleFileConverter->expects($this->once())
            ->method('toJson')
            ->with($moduleFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willReturn('SERIALIZED');

        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->saveModuleFile($moduleFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveModuleFileConvertsEncodingFailedException()
    {
        $moduleFile = new ModuleFile(null, '/path');
        $moduleFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->moduleFileConverter->expects($this->once())
            ->method('toJson')
            ->with($moduleFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willThrowException(new EncodingFailedException());

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveModuleFile($moduleFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveModuleFileConvertsConversionFailedException()
    {
        $moduleFile = new ModuleFile(null, '/path');
        $moduleFile->setVersion('1.0');

        $this->moduleFileConverter->expects($this->once())
            ->method('toJson')
            ->with($moduleFile, array('targetVersion' => '1.0'))
            ->willThrowException(new ConversionFailedException());

        $this->jsonEncoder->expects($this->never())
            ->method('encode');

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveModuleFile($moduleFile);
    }

    public function testLoadRootModuleFile()
    {
        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, '/path', $baseConfig);
        $jsonData = new stdClass();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willReturn($jsonData);

        $this->rootModuleFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path', 'baseConfig' => $baseConfig))
            ->will($this->returnValue($moduleFile));

        $this->assertSame($moduleFile, $this->storage->loadRootModuleFile('/path', $baseConfig));
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadRootModuleFileConvertsDecodingFailedException()
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

        $this->rootModuleFileConverter->expects($this->never())
            ->method('fromJson');

        $this->storage->loadRootModuleFile('/path', $baseConfig);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadRootModuleFileConvertsConversionFailedException()
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

        $this->rootModuleFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path', 'baseConfig' => $baseConfig))
            ->willThrowException(new ConversionFailedException());

        $this->storage->loadRootModuleFile('/path', $baseConfig);
    }

    public function testSaveRootModuleFile()
    {
        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, '/path', $baseConfig);
        $moduleFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->rootModuleFileConverter->expects($this->once())
            ->method('toJson')
            ->with($moduleFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willReturn('SERIALIZED');

        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->saveRootModuleFile($moduleFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveRootModuleFileConvertsEncodingFailedException()
    {
        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, '/path', $baseConfig);
        $moduleFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->rootModuleFileConverter->expects($this->once())
            ->method('toJson')
            ->with($moduleFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willThrowException(new EncodingFailedException());

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveRootModuleFile($moduleFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveRootModuleFileConvertsConversionFailedException()
    {
        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, '/path', $baseConfig);
        $moduleFile->setVersion('1.0');

        $this->rootModuleFileConverter->expects($this->once())
            ->method('toJson')
            ->with($moduleFile, array('targetVersion' => '1.0'))
            ->willThrowException(new ConversionFailedException());

        $this->jsonEncoder->expects($this->never())
            ->method('encode');

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveRootModuleFile($moduleFile);
    }

    public function testSaveRootModuleFileGeneratesFactoryIfManagerAvailable()
    {
        $factoryManager = $this->getMock('Puli\Manager\Api\Factory\FactoryManager');
        $baseConfig = new Config();
        $moduleFile = new RootModuleFile(null, '/path', $baseConfig);
        $moduleFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->storage = new JsonStorage(
            $this->backend,
            $this->converterProvider,
            $this->jsonEncoder,
            $this->jsonDecoder,
            $factoryManager
        );

        $this->rootModuleFileConverter->expects($this->once())
            ->method('toJson')
            ->with($moduleFile, array('targetVersion' => '1.0'))
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

        $this->storage->saveRootModuleFile($moduleFile);
    }
}
