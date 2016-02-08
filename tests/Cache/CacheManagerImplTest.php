<?php

/*
 * This file is part of the puli/manager module.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Cache;

use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Cache\CacheFile;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleManager;
use Puli\Manager\Cache\CacheManagerImpl;
use Puli\Manager\Json\JsonStorage;
use Puli\Manager\Tests\ManagerTestCase;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Mateusz Sojda <mateusz@sojda.pl>
 */
class CacheManagerImplTest extends ManagerTestCase
{
    /**
     * @var Module
     */
    private $module1;

    /**
     * @var Module
     */
    private $module2;

    /**
     * @var Module
     */
    private $module3;

    /**
     * @var ModuleFile
     */
    private $moduleFile1;

    /**
     * @var ModuleFile
     */
    private $moduleFile2;

    /**
     * @var ModuleFile
     */
    private $moduleFile3;

    /**
     * @var ModuleList
     */
    private $moduleList;

    /**
     * @var InstallInfo
     */
    private $installInfo1;

    /**
     * @var InstallInfo
     */
    private $installInfo2;

    /**
     * @var InstallInfo
     */
    private $installInfo3;

    /**
     * @var CacheFile
     */
    private $cacheFile;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ModuleManager
     */
    private $moduleManager;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|JsonStorage
     */
    private $jsonStorage;

    protected function setUp()
    {
        $this->moduleFile1 = new ModuleFile('vendor/module1');
        $this->moduleFile2 = new ModuleFile('vendor/module2');
        $this->moduleFile3 = new ModuleFile('vendor/module3');

        $this->installInfo1 = new InstallInfo('vendor/module1', '../module1');
        $this->installInfo2 = new InstallInfo('vendor/module2', '../module2');
        $this->installInfo3 = new InstallInfo('vendor/module3', '../module3');

        $this->module1 = new Module(
            $this->moduleFile1,
            Path::makeAbsolute('../module1', __DIR__.'/Fixtures/root'),
            $this->installInfo1
        );

        $this->module2 = new Module(
            $this->moduleFile2,
            Path::makeAbsolute('../module2', __DIR__.'/Fixtures/root'),
            $this->installInfo2
        );
        $this->module3 = new Module(
            $this->moduleFile3,
            Path::makeAbsolute('../module3', __DIR__.'/Fixtures/root'),
            $this->installInfo3
        );

        $this->moduleList = new ModuleList(array(
            $this->module1,
            $this->module2,
            $this->module3,
        ));

        $this->cacheFile = new CacheFile();

        $this->cacheFile->addModuleFile($this->moduleFile1);
        $this->cacheFile->addModuleFile($this->moduleFile2);
        $this->cacheFile->addModuleFile($this->moduleFile3);

        $this->cacheFile->addInstallInfo($this->installInfo1);
        $this->cacheFile->addInstallInfo($this->installInfo2);
        $this->cacheFile->addInstallInfo($this->installInfo3);

        $this->moduleManager = $this->getMock('Puli\Manager\Api\Module\ModuleManager');

        $this->moduleManager->expects($this->any())->method('findModules')->willReturn($this->moduleList);

        $this->jsonStorage = $this->getMockBuilder('Puli\Manager\Json\JsonStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->jsonStorage->expects($this->any())->method('loadCacheFile')->willReturn($this->cacheFile);

        $this->initContext(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');
    }

    protected function tearDown()
    {
        $this->killCacheFile();
        $this->killRootModuleFile();
    }

    public function testGetCacheFileIfExists()
    {
        $this->jsonStorage->expects($this->any())->method('fileExists')->willReturn(true);

        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $this->assertEquals($this->cacheFile, $cacheManager->getCacheFile());
    }

    public function testGetCacheFileIfNotExists()
    {
        $this->killCacheFile();

        $this->jsonStorage->expects($this->any())->method('fileExists')->willReturn(false);

        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $this->assertEquals($this->cacheFile, $cacheManager->getCacheFile());
    }

    public function testRefreshCacheFileIfCacheFileNotExists()
    {
        $this->killCacheFile();

        $this->jsonStorage->expects($this->any())->method('fileExists')->willReturn(false);
        $this->jsonStorage->expects($this->once())->method('saveCacheFile');

        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $cacheManager->refreshCacheFile();
    }

    public function testRefreshCacheFileIfCacheFileExistsAndRootModuleFileHasBeenNotModified()
    {
        $this->ensureRootModuleFileExists();
        sleep(1);
        $this->ensureCacheFileExists();

        $this->jsonStorage->expects($this->any())->method('fileExists')->willReturn(true);
        $this->jsonStorage->expects($this->never())->method('saveCacheFile');

        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $cacheManager->refreshCacheFile();
    }

    public function testRefreshCacheFileIfCacheFileExistsAndRootModuleFileHasBeenModified()
    {
        $this->ensureCacheFileExists();
        sleep(1);
        $this->ensureRootModuleFileExists();

        $this->jsonStorage->expects($this->any())->method('fileExists')->willReturn(true);
        $this->jsonStorage->expects($this->once())->method('saveCacheFile');

        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $cacheManager->refreshCacheFile();
    }

    public function testRefreshCacheFileIfCacheFileExistsAndRootModuleFileNotExist()
    {
        $this->killRootModuleFile();
        $this->ensureCacheFileExists();

        $this->jsonStorage->expects($this->at(0))->method('fileExists')->willReturn(true);
        $this->jsonStorage->expects($this->at(1))->method('fileExists')->willReturn(false);
        $this->jsonStorage->expects($this->once())->method('saveCacheFile');

        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $cacheManager->refreshCacheFile();
    }

    public function testGetModule()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $module = $cacheManager->getModule('vendor/module1');

        $this->assertInstanceOf('Puli\Manager\Api\Module\Module', $module);
        $this->assertSame($this->moduleFile1, $module->getModuleFile());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetModuleWithInvalidName()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $cacheManager->getModule(1);
    }

    public function testGetRootModule()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $rootModule = $cacheManager->getRootModule();

        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModule', $rootModule);
        $this->assertSame('vendor/root', $rootModule->getName());
        $this->assertSame($this->rootDir, $rootModule->getInstallPath());
        $this->assertSame($this->rootModuleFile, $rootModule->getModuleFile());
    }

    public function testGetModules()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $modules = $cacheManager->getModules();

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertEquals(4, $modules->count());
        $this->assertTrue($modules->contains('vendor/module1'));
        $this->assertTrue($modules->contains('vendor/module2'));
        $this->assertTrue($modules->contains('vendor/module3'));
    }

    public function testFindModules()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $this->installInfo2->setInstallerName('puli');

        $expr1 = Expr::method('isEnabled', Expr::same(true));
        $expr2 = Expr::method('getInstallInfo', Expr::method('getInstallerName', Expr::same('puli')));
        $expr3 = $expr1->andX($expr2);

        $modules = $cacheManager->findModules($expr1);

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertTrue($modules->contains('vendor/root'));
        $this->assertTrue($modules->contains('vendor/module2'));
        $this->assertCount(4, $modules);

        $modules = $cacheManager->findModules($expr2);

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertTrue($modules->contains('vendor/module2'));
        $this->assertCount(1, $modules);

        $modules = $cacheManager->findModules($expr3);

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertCount(1, $modules);
    }

    public function testHasModule()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $this->assertTrue($cacheManager->hasModule('vendor/module1'));
        $this->assertTrue($cacheManager->hasModule('vendor/module2'));
        $this->assertFalse($cacheManager->hasModule('foo/bar'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testHasModuleWithInvalidName()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $cacheManager->hasModule(1);
    }

    public function testHasModules()
    {
        $cacheManager = new CacheManagerImpl($this->moduleManager, $this->jsonStorage, $this->context);

        $this->assertTrue($cacheManager->hasModules());
        $this->assertTrue($cacheManager->hasModules(Expr::method('getName', Expr::same('vendor/module1'))));
        $this->assertFalse($cacheManager->hasModules(Expr::method('getName', Expr::same('foo/bar'))));
    }

    private function ensureCacheFileExists()
    {
        $path = $this->context->getConfig()->get(Config::CACHE_FILE);
        $path = Path::makeAbsolute($path, $this->rootDir);

        if (false === file_exists(Path::makeAbsolute('.puli', $this->rootDir))) {
            mkdir(Path::makeAbsolute('.puli', $this->rootDir));
        }

        file_put_contents($path, '', FILE_APPEND);
    }

    private function ensureRootModuleFileExists()
    {
        $rootModuleFilePath = $this->context->getRootModuleFile()->getPath();
        file_put_contents($rootModuleFilePath, '', FILE_APPEND);
    }

    private function killRootModuleFile()
    {
        $rootModuleFilePath = $this->context->getRootModuleFile()->getPath();
        @unlink($rootModuleFilePath);
    }

    private function killCacheFile()
    {
        $path = $this->context->getConfig()->get(Config::CACHE_FILE);
        $path = Path::makeAbsolute($path, $this->rootDir);
        @unlink($path);
    }
}
