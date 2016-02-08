<?php

/*
 * This file is part of the puli/manager module.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Cache;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Cache\CacheFile;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\ModuleFile;

/**
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
class CacheFileTest extends PHPUnit_Framework_TestCase
{
    public function provideValidPaths()
    {
        return array(
            array(null),
            array('/foo'),
        );
    }

    /**
     * @dataProvider provideValidPaths
     */
    public function testGetPath($path)
    {
        $cacheFile = new CacheFile($path);

        $this->assertSame($path, $cacheFile->getPath());
    }

    public function provideInvalidPaths()
    {
        return array(
            array(12345),
            array(''),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustBeValid($invalidPath)
    {
        new CacheFile($invalidPath);
    }

    public function provideValidModuleFiles()
    {
        return array(
            array(array(
                new ModuleFile('vendor/module1'),
                new ModuleFile('vendor/module2'),
                new ModuleFile('vendor/module3'),
            )),
            array(array()),
        );
    }

    /**
     * @dataProvider provideValidModuleFiles
     */
    public function testModuleFilesSetter($moduleFiles)
    {
        $cacheFile = new CacheFile();

        $cacheFile->setModuleFiles($moduleFiles);

        $this->assertEquals(count($moduleFiles), count($cacheFile->getModuleFiles()));

        foreach ($cacheFile->getModuleFiles() as $moduleFile) {
            if (false === in_array($moduleFile, $moduleFiles)) {
                $this->fail();
            }
        }
    }

    public function testAddingModuleFile()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $moduleFile2 = new ModuleFile('vendor/module2');
        $moduleFile3 = new ModuleFile('vendor/module3');

        $cacheFile = new CacheFile();

        $cacheFile->addModuleFile($moduleFile1);
        $cacheFile->addModuleFile($moduleFile2);
        $cacheFile->addModuleFile($moduleFile3);

        $this->assertEquals(3, count($cacheFile->getModuleFiles()));

        $this->assertTrue($cacheFile->hasModuleFile('vendor/module1'));
        $this->assertTrue($cacheFile->hasModuleFile('vendor/module2'));
        $this->assertTrue($cacheFile->hasModuleFile('vendor/module3'));
    }

    public function testRemovingModuleFile()
    {
        $cacheFile = new CacheFile();

        $moduleFile1 = new ModuleFile('vendor/module1');
        $moduleFile2 = new ModuleFile('vendor/module2');

        $cacheFile->addModuleFile($moduleFile1);
        $cacheFile->addModuleFile($moduleFile2);

        $this->assertEquals(2, count($cacheFile->getModuleFiles()));

        $cacheFile->removeModuleFile('vendor/module1');

        $this->assertEquals(1, count($cacheFile->getModuleFiles()));
        $this->assertFalse($cacheFile->hasModuleFile('vendor/module1'));
    }

    public function testClearingModuleFiles()
    {
        $cacheFile = new CacheFile();

        $moduleFile1 = new ModuleFile('vendor/module1');
        $moduleFile2 = new ModuleFile('vendor/module2');

        $cacheFile->addModuleFile($moduleFile1);
        $cacheFile->addModuleFile($moduleFile2);

        $this->assertEquals(2, count($cacheFile->getModuleFiles()));

        $cacheFile->clearModuleFiles();

        $this->assertEquals(0, count($cacheFile->getModuleFiles()));
        $this->assertFalse($cacheFile->hasModuleFiles());
    }

    public function testFetchingModuleFile()
    {
        $cacheFile = new CacheFile();

        $moduleFile1 = new ModuleFile('vendor/module1');
        $moduleFile2 = new ModuleFile('vendor/module2');

        $cacheFile->addModuleFile($moduleFile1);
        $cacheFile->addModuleFile($moduleFile2);

        $fetchedModuleFile = $cacheFile->getModuleFile('vendor/module1');

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleFile', $fetchedModuleFile);
        $this->assertSame($moduleFile1, $fetchedModuleFile);
    }

    public function testIfHasModuleFile()
    {
        $cacheFile = new CacheFile();

        $moduleFile1 = new ModuleFile('vendor/module1');
        $moduleFile2 = new ModuleFile('vendor/module2');

        $cacheFile->addModuleFile($moduleFile1);
        $cacheFile->addModuleFile($moduleFile2);

        $this->assertTrue($cacheFile->hasModuleFile('vendor/module1'));
        $this->assertTrue($cacheFile->hasModuleFile('vendor/module2'));
    }

    public function testIfHasAnyModuleFiles()
    {
        $cacheFile = new CacheFile();

        $moduleFile1 = new ModuleFile('vendor/module1');
        $moduleFile2 = new ModuleFile('vendor/module2');

        $cacheFile->addModuleFile($moduleFile1);
        $cacheFile->addModuleFile($moduleFile2);

        $this->assertTrue($cacheFile->hasModuleFiles());
    }

    public function testAddingInstallInfo()
    {
        $cacheFile = new CacheFile();

        $installInfo1 = new InstallInfo('vendor/module1', '/path/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/module2');
        $installInfo3 = new InstallInfo('vendor/module3', '/path/module3');

        $cacheFile->addInstallInfo($installInfo1);
        $cacheFile->addInstallInfo($installInfo2);
        $cacheFile->addInstallInfo($installInfo3);

        $this->assertEquals(3, count($cacheFile->getInstallInfos()));

        $this->assertTrue($cacheFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($cacheFile->hasInstallInfo('vendor/module2'));
        $this->assertTrue($cacheFile->hasInstallInfo('vendor/module3'));
    }

    public function testRemovingInstallInfo()
    {
        $cacheFile = new CacheFile();

        $installInfo1 = new InstallInfo('vendor/module1', '/path/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/module2');

        $cacheFile->addInstallInfo($installInfo1);
        $cacheFile->addInstallInfo($installInfo2);

        $this->assertEquals(2, count($cacheFile->getInstallInfos()));

        $cacheFile->removeInstallInfo('vendor/module1');

        $this->assertFalse($cacheFile->hasInstallInfo('vendor/module1'));
        $this->assertEquals(1, count($cacheFile->getInstallInfos()));
    }

    public function testClearingInstallInfos()
    {
        $cacheFile = new CacheFile();

        $installInfo1 = new InstallInfo('vendor/module1', '/path/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/module2');

        $cacheFile->addInstallInfo($installInfo1);
        $cacheFile->addInstallInfo($installInfo2);

        $this->assertEquals(2, count($cacheFile->getInstallInfos()));

        $cacheFile->clearInstallInfo();

        $this->assertEquals(0, count($cacheFile->getInstallInfos()));
        $this->assertFalse($cacheFile->hasInstallInfos());
    }

    public function testFetchingInstallInfo()
    {
        $cacheFile = new CacheFile();

        $installInfo = new InstallInfo('vendor/module1', '/path/module1');

        $cacheFile->addInstallInfo($installInfo);

        $fetchedInstallInfo = $cacheFile->getInstallInfo('vendor/module1');

        $this->assertInstanceOf('Puli\Manager\Api\Module\InstallInfo', $fetchedInstallInfo);
        $this->assertSame($installInfo, $fetchedInstallInfo);
    }

    public function testIfHasInstallInfo()
    {
        $cacheFile = new CacheFile();

        $installInfo1 = new InstallInfo('vendor/module1', '/path/module1');
        $installInfo2 = new InstallInfo('vendor/module2', '/path/module2');

        $cacheFile->addInstallInfo($installInfo1);
        $cacheFile->addInstallInfo($installInfo2);

        $this->assertTrue($cacheFile->hasInstallInfo('vendor/module2'));

        $cacheFile->clearInstallInfo();

        $this->assertFalse($cacheFile->hasInstallInfo('vendor/module2'));
    }

    public function testIfHasAnyInstallInfos()
    {
        $cacheFile = new CacheFile();

        $installInfo = new InstallInfo('vendor/module1', '/path/module1');

        $cacheFile->addInstallInfo($installInfo);

        $this->assertTrue($cacheFile->hasInstallInfos());

        $cacheFile->clearInstallInfo();

        $this->assertFalse($cacheFile->hasInstallInfos());
    }
}
