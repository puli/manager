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
use Puli\Manager\Api\Package\PackageFileTransformer;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Package\PackageFileStorage;
use stdClass;
use Webmozart\Json\Conversion\ConversionException;
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
class PackageFileStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PackageFileStorage
     */
    private $storage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverter
     */
    private $packageFileConverter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverter
     */
    private $rootPackageFileConverter;

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
        $this->packageFileConverter = $this->getMock('Webmozart\Json\Conversion\JsonConverter');
        $this->rootPackageFileConverter = $this->getMock('Webmozart\Json\Conversion\JsonConverter');
        $this->jsonEncoder = $this->getMockBuilder('Webmozart\Json\JsonEncoder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->jsonDecoder = $this->getMockBuilder('Webmozart\Json\JsonDecoder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->backend = $this->getMock('Puli\Manager\Api\Storage\Storage');

        $this->storage = new PackageFileStorage(
            $this->backend,
            $this->packageFileConverter,
            $this->rootPackageFileConverter,
            $this->jsonEncoder,
            $this->jsonDecoder
        );
    }

    public function testLoadPackageFile()
    {
        $packageFile = new PackageFile(null);
        $jsonData = new stdClass();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willReturn($jsonData);

        $this->packageFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path'))
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadPackageFile('/path'));
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadPackageFileConvertsDecodingFailedException()
    {
        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willThrowException(new DecodingFailedException());

        $this->packageFileConverter->expects($this->never())
            ->method('fromJson');

        $this->storage->loadPackageFile('/path');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadPackageFileConvertsConversionException()
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

        $this->packageFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path'))
            ->willThrowException(new ConversionException());

        $this->storage->loadPackageFile('/path');
    }

    public function testSavePackageFile()
    {
        $packageFile = new PackageFile(null, '/path');
        $packageFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->packageFileConverter->expects($this->once())
            ->method('toJson')
            ->with($packageFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willReturn('SERIALIZED');

        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->savePackageFile($packageFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSavePackageFileConvertsEncodingFailedException()
    {
        $packageFile = new PackageFile(null, '/path');
        $packageFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->packageFileConverter->expects($this->once())
            ->method('toJson')
            ->with($packageFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willThrowException(new EncodingFailedException());

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->savePackageFile($packageFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSavePackageFileConvertsConversionException()
    {
        $packageFile = new PackageFile(null, '/path');
        $packageFile->setVersion('1.0');

        $this->packageFileConverter->expects($this->once())
            ->method('toJson')
            ->with($packageFile, array('targetVersion' => '1.0'))
            ->willThrowException(new ConversionException());

        $this->jsonEncoder->expects($this->never())
            ->method('encode');

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->savePackageFile($packageFile);
    }

    public function testLoadRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);
        $jsonData = new stdClass();

        $this->backend->expects($this->once())
            ->method('read')
            ->with('/path')
            ->willReturn('SERIALIZED');

        $this->jsonDecoder->expects($this->once())
            ->method('decode')
            ->with('SERIALIZED')
            ->willReturn($jsonData);

        $this->rootPackageFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path', 'baseConfig' => $baseConfig))
            ->will($this->returnValue($packageFile));

        $this->assertSame($packageFile, $this->storage->loadRootPackageFile('/path', $baseConfig));
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadRootPackageFileConvertsDecodingFailedException()
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

        $this->rootPackageFileConverter->expects($this->never())
            ->method('fromJson');

        $this->storage->loadRootPackageFile('/path', $baseConfig);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testLoadRootPackageFileConvertsConversionException()
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

        $this->rootPackageFileConverter->expects($this->once())
            ->method('fromJson')
            ->with($jsonData, array('path' => '/path', 'baseConfig' => $baseConfig))
            ->willThrowException(new ConversionException());

        $this->storage->loadRootPackageFile('/path', $baseConfig);
    }

    public function testSaveRootPackageFile()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);
        $jsonData = new stdClass();

        $this->rootPackageFileConverter->expects($this->once())
            ->method('toJson')
            ->with($packageFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willReturn('SERIALIZED');

        $this->backend->expects($this->once())
            ->method('write')
            ->with('/path', 'SERIALIZED');

        $this->storage->saveRootPackageFile($packageFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveRootPackageFileConvertsEncodingFailedException()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);
        $packageFile->setVersion('1.0');
        $jsonData = new stdClass();

        $this->rootPackageFileConverter->expects($this->once())
            ->method('toJson')
            ->with($packageFile, array('targetVersion' => '1.0'))
            ->willReturn($jsonData);

        $this->jsonEncoder->expects($this->once())
            ->method('encode')
            ->with($jsonData)
            ->willThrowException(new EncodingFailedException());

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveRootPackageFile($packageFile);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testSaveRootPackageFileConvertsConversionException()
    {
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);
        $packageFile->setVersion('1.0');

        $this->rootPackageFileConverter->expects($this->once())
            ->method('toJson')
            ->with($packageFile, array('targetVersion' => '1.0'))
            ->willThrowException(new ConversionException());

        $this->jsonEncoder->expects($this->never())
            ->method('encode');

        $this->backend->expects($this->never())
            ->method('write');

        $this->storage->saveRootPackageFile($packageFile);
    }

    public function testSaveRootPackageFileGeneratesFactoryIfManagerAvailable()
    {
        $factoryManager = $this->getMock('Puli\Manager\Api\Factory\FactoryManager');
        $baseConfig = new Config();
        $packageFile = new RootPackageFile(null, '/path', $baseConfig);
        $jsonData = new stdClass();

        $this->storage = new PackageFileStorage(
            $this->backend,
            $this->packageFileConverter,
            $this->rootPackageFileConverter,
            $this->jsonEncoder,
            $this->jsonDecoder,
            $factoryManager
        );

        $this->rootPackageFileConverter->expects($this->once())
            ->method('toJson')
            ->with($packageFile, array('targetVersion' => '1.0'))
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

        $this->storage->saveRootPackageFile($packageFile);
    }
}
