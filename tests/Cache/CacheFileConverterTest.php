<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Cache;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Cache\CacheFile;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Cache\CacheFileConverter;
use stdClass;
use Webmozart\Json\Conversion\JsonConverter;

/**
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
class CacheFileConverterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ModuleFile
     */
    private $moduleFile1;

    /**
     * @var ModuleFile
     */
    private $moduleFile2;

    /**
     * @var stdClass
     */
    private $jsonDataModule1;

    /**
     * @var stdClass
     */
    private $jsonDataModule2;

    /**
     * @var InstallInfo
     */
    private $installInfo1;

    /**
     * @var InstallInfo
     */
    private $installInfo2;

    /**
     * @var CacheFileConverter
     */
    private $converter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonConverter
     */
    private $moduleFileConverter;

    protected function setUp()
    {
        $this->moduleFile1 = new ModuleFile('vendor/module1');
        $this->moduleFile2 = new ModuleFile('vendor/module2');

        $this->installInfo1 = new InstallInfo('vendor/module1', '../module1');
        $this->installInfo2 = new InstallInfo('vendor/module2', '../module2');

        $this->jsonDataModule1 = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'name' => 'vendor/module1',
        );
        $this->jsonDataModule2 = (object) array(
            '$schema' => 'http://puli.io/schema/2.0/manager/module',
            'name' => 'vendor/module2',
        );

        $this->moduleFileConverter = $this->getMock('Webmozart\Json\Conversion\JsonConverter');

        $this->converter = new CacheFileConverter($this->moduleFileConverter);
    }

    public function testToJson()
    {
        $this->moduleFileConverter
            ->expects($this->at(0))
            ->method('toJson')->with($this->moduleFile1, array(
                'targetVersion' => $this->moduleFile1->getVersion(),
            ))
            ->willReturn($this->jsonDataModule1);

        $this->moduleFileConverter
            ->expects($this->at(1))
            ->method('toJson')->with($this->moduleFile2, array(
                'targetVersion' => $this->moduleFile2->getVersion(),
            ))
            ->willReturn($this->jsonDataModule2);

        $cacheFile = new CacheFile('/path');

        $cacheFile->addModuleFile($this->moduleFile1);
        $cacheFile->addModuleFile($this->moduleFile2);

        $cacheFile->addInstallInfo($this->installInfo1);
        $cacheFile->addInstallInfo($this->installInfo2);

        $jsonData = (object) array(
            'modules' => array(
                $this->jsonDataModule1,
                $this->jsonDataModule2,
            ),
            'installInfos' => array(
                'vendor/module1' => '../module1',
                'vendor/module2' => '../module2',
            ),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($cacheFile));
    }

    public function testToJsonWithEmptyModules()
    {
        $this->moduleFileConverter
            ->expects($this->never())
            ->method('toJson');

        $cacheFile = new CacheFile('/path');

        $jsonData = (object) array(
            'modules' => array(),
            'installInfos' => array(),
        );

        $this->assertEquals($jsonData, $this->converter->toJson($cacheFile));
    }

    public function testFromJson()
    {
        $this->moduleFileConverter
            ->expects($this->at(0))
            ->method('fromJson')
            ->with($this->jsonDataModule1)
            ->willReturn($this->moduleFile1);

        $this->moduleFileConverter
            ->expects($this->at(1))
            ->method('fromJson')
            ->with($this->jsonDataModule2)
            ->willReturn($this->moduleFile2);

        $jsonData = (object) array(
            'modules' => array(
                $this->jsonDataModule1,
                $this->jsonDataModule2,
            ),
            'installInfos' => array(
                'vendor/module1' => '../module1',
                'vendor/module2' => '../module2',
            ),
        );

        $cacheFile = $this->converter->fromJson($jsonData, array(
            'path' => '/path',
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Cache\CacheFile', $cacheFile);
        $this->assertEquals('/path', $cacheFile->getPath());
        $this->assertEquals(2, count($cacheFile->getModuleFiles()));
        $this->assertTrue($cacheFile->hasModuleFile('vendor/module1'));
        $this->assertTrue($cacheFile->hasModuleFile('vendor/module2'));
        $this->assertTrue($cacheFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($cacheFile->hasInstallInfo('vendor/module2'));
    }

    public function testFromJsonWithEmptyModules()
    {
        $this->moduleFileConverter
            ->expects($this->never())
            ->method('fromJson');

        $jsonData = (object) array(
            'modules' => array(),
            'installInfos' => array(),
        );

        $cacheFile = $this->converter->fromJson($jsonData, array(
            'path' => '/path',
        ));

        $this->assertInstanceOf('Puli\Manager\Api\Cache\CacheFile', $cacheFile);
        $this->assertEquals('/path', $cacheFile->getPath());
        $this->assertEquals(0, count($cacheFile->getModuleFiles()));
        $this->assertFalse($cacheFile->hasModuleFiles());
        $this->assertFalse($cacheFile->hasInstallInfos());
    }

    public function testFromJsonWithEmptyPath()
    {
        $jsonData = (object) array(
            'modules' => array(),
            'installInfos' => array(),
        );

        $cacheFile = $this->converter->fromJson($jsonData);

        $this->assertInstanceOf('Puli\Manager\Api\Cache\CacheFile', $cacheFile);
        $this->assertNull($cacheFile->getPath());
    }
}
