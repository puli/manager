<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Module;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Module\UnsupportedVersionException;
use Puli\Manager\Json\JsonStorage;
use Puli\Manager\Module\ModuleManagerImpl;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Manager\Tests\TestException;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expr;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleManagerImplTest extends ManagerTestCase
{
    /**
     * @var string
     */
    private $moduleDir1;

    /**
     * @var string
     */
    private $moduleDir2;

    /**
     * @var string
     */
    private $moduleDir3;

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
     * @var PHPUnit_Framework_MockObject_MockObject|JsonStorage
     */
    private $jsonStorage;

    /**
     * @var ModuleManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        $this->moduleDir1 = Path::normalize(__DIR__.'/Fixtures/module1');
        $this->moduleDir2 = Path::normalize(__DIR__.'/Fixtures/module2');
        $this->moduleDir3 = Path::normalize(__DIR__.'/Fixtures/module3');

        $this->moduleFile1 = new ModuleFile();
        $this->moduleFile2 = new ModuleFile();
        $this->moduleFile3 = new ModuleFile('vendor/module3');

        $this->installInfo1 = new InstallInfo('vendor/module1', '../module1');
        $this->installInfo2 = new InstallInfo('vendor/module2', '../module2');
        $this->installInfo3 = new InstallInfo('vendor/module3', '../module3');

        $this->jsonStorage = $this->getMockBuilder('Puli\Manager\Json\JsonStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->jsonStorage->expects($this->any())
            ->method('loadModuleFile')
            ->willReturnMap(array(
                array($this->moduleDir1.'/puli.json', $this->moduleFile1),
                array($this->moduleDir2.'/puli.json', $this->moduleFile2),
                array($this->moduleDir3.'/puli.json', $this->moduleFile3),
            ));

        $this->initContext(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');
    }

    protected function tearDown()
    {
        // Make sure initDefaultManager() is called again
        $this->manager = null;
    }

    public function testGetModules()
    {
        $this->rootModuleFile->addInstallInfo($installInfo1 = new InstallInfo('vendor/module1', '../foo'));
        $this->rootModuleFile->addInstallInfo($installInfo2 = new InstallInfo('vendor/module2', $this->moduleDir2));

        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);

        $modules = $manager->getModules();

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertTrue($modules->contains('vendor/root'));
        $this->assertTrue($modules->contains('vendor/module1'));
        $this->assertTrue($modules->contains('vendor/module2'));
        $this->assertCount(3, $modules);
    }

    public function testFindModules()
    {
        $this->rootModuleFile->addInstallInfo($installInfo1 = new InstallInfo('vendor/module1', '../foo'));
        $this->rootModuleFile->addInstallInfo($installInfo2 = new InstallInfo('vendor/module2', $this->moduleDir2));

        $installInfo1->setInstallerName('webmozart');

        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);

        $expr1 = Expr::method('isEnabled', Expr::same(true));
        $expr2 = Expr::method('getInstallInfo', Expr::method('getInstallerName', Expr::same('webmozart')));
        $expr3 = $expr1->andX($expr2);

        $modules = $manager->findModules($expr1);

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertTrue($modules->contains('vendor/root'));
        $this->assertTrue($modules->contains('vendor/module2'));
        $this->assertCount(2, $modules);

        $modules = $manager->findModules($expr2);

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertTrue($modules->contains('vendor/module1'));
        $this->assertCount(1, $modules);

        $modules = $manager->findModules($expr3);

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleList', $modules);
        $this->assertCount(0, $modules);
    }

    public function testGetModulesStoresNoModuleFileIfNotFound()
    {
        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);

        $this->rootModuleFile->addInstallInfo(new InstallInfo('vendor/module', $this->moduleDir1));

        $exception = new FileNotFoundException();

        $this->jsonStorage->expects($this->once())
            ->method('loadModuleFile')
            ->with($this->moduleDir1.'/puli.json')
            ->willThrowException($exception);

        $modules = $manager->getModules();

        $this->assertTrue($modules['vendor/module']->isEnabled());
        $this->assertNull($modules['vendor/module']->getModuleFile());
    }

    public function testGetModulesStoresExceptionIfModuleDirectoryNotFound()
    {
        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);

        $this->rootModuleFile->addInstallInfo(new InstallInfo('vendor/module', 'foobar'));

        $modules = $manager->getModules();

        $this->assertTrue($modules['vendor/module']->isNotFound());

        $loadErrors = $modules['vendor/module']->getLoadErrors();

        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\Manager\Api\FileNotFoundException', $loadErrors[0]);
    }

    public function testGetModulesStoresExceptionIfModuleNoDirectory()
    {
        $this->rootModuleFile->addInstallInfo(new InstallInfo('vendor/module', __DIR__.'/Fixtures/file'));

        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);

        $modules = $manager->getModules();

        $this->assertTrue($modules['vendor/module']->isNotLoadable());

        $loadErrors = $modules['vendor/module']->getLoadErrors();

        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\Manager\Api\NoDirectoryException', $loadErrors[0]);
    }

    public function testGetModulesStoresExceptionIfModuleFileVersionNotSupported()
    {
        $this->rootModuleFile->addInstallInfo(new InstallInfo('vendor/module', $this->moduleDir1));

        $exception = new UnsupportedVersionException();

        $this->jsonStorage->expects($this->once())
            ->method('loadModuleFile')
            ->with($this->moduleDir1.'/puli.json')
            ->willThrowException($exception);

        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);

        $modules = $manager->getModules();

        $this->assertTrue($modules['vendor/module']->isNotLoadable());
        $this->assertSame(array($exception), $modules['vendor/module']->getLoadErrors());
    }

    public function testGetModulesStoresExceptionIfModuleFileInvalid()
    {
        $this->rootModuleFile->addInstallInfo(new InstallInfo('vendor/module', $this->moduleDir1));

        $exception = new InvalidConfigException();

        $this->jsonStorage->expects($this->once())
            ->method('loadModuleFile')
            ->with($this->moduleDir1.'/puli.json')
            ->willThrowException($exception);

        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);

        $modules = $manager->getModules();

        $this->assertTrue($modules['vendor/module']->isNotLoadable());
        $this->assertSame(array($exception), $modules['vendor/module']->getLoadErrors());
    }

    public function testHasModule()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->hasModule('vendor/root'));
        $this->assertTrue($this->manager->hasModule('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/module2'));
        $this->assertFalse($this->manager->hasModule('vendor/module3'));
    }

    public function testHasModules()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->hasModules());
        $this->assertTrue($this->manager->hasModules(Expr::method('getName', Expr::same('vendor/root'))));
        $this->assertFalse($this->manager->hasModules(Expr::method('getName', Expr::same('foobar'))));
    }

    public function testInstallModule()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $installInfos = $rootModuleFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../module1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../module2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../module3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installModule($this->moduleDir3);
    }

    public function testInstallModuleWithRelativePath()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $installInfos = $rootModuleFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../module1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../module2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../module3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installModule('../module3');
    }

    public function testInstallModuleWithCustomName()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $installInfos = $rootModuleFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../module1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('vendor/module1', $installInfos[0]->getModuleName());
                PHPUnit_Framework_Assert::assertSame('../module2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('vendor/module2', $installInfos[1]->getModuleName());
                PHPUnit_Framework_Assert::assertSame('../module3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('my/module3-custom', $installInfos[2]->getModuleName());
            }));

        $this->manager->installModule($this->moduleDir3, 'my/module3-custom');
    }

    public function testInstallModuleInDevEnvironment()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $installInfos = $rootModuleFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../module1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame(Environment::PROD, $installInfos[0]->getEnvironment());
                PHPUnit_Framework_Assert::assertSame('../module2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame(Environment::PROD, $installInfos[1]->getEnvironment());
                PHPUnit_Framework_Assert::assertSame('../module3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame(Environment::DEV, $installInfos[2]->getEnvironment());
            }));

        $this->manager->installModule($this->moduleDir3, null, InstallInfo::DEFAULT_INSTALLER_NAME, Environment::DEV);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage module3
     */
    public function testInstallModuleFailsIfNoVendorPrefix()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->installModule($this->moduleDir3, 'module3');
    }

    public function testInstallModuleWithCustomInstaller()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $installInfos = $rootModuleFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../module1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('user', $installInfos[0]->getInstallerName());
                PHPUnit_Framework_Assert::assertSame('../module2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('user', $installInfos[1]->getInstallerName());
                PHPUnit_Framework_Assert::assertSame('../module3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('composer', $installInfos[2]->getInstallerName());
            }));

        $this->manager->installModule($this->moduleDir3, null, 'composer');
    }

    public function testInstallModuleDoesNothingIfAlreadyInstalled()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->installModule($this->moduleDir2);
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\NameConflictException
     */
    public function testInstallModuleFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->moduleFile3->setModuleName('vendor/module2');

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->installModule($this->moduleDir3);
    }

    /**
     * @expectedException \Puli\Manager\Api\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testInstallModuleFailsIfDirectoryNotFound()
    {
        $this->initDefaultManager();

        $this->manager->installModule(__DIR__.'/foobar');
    }

    /**
     * @expectedException \Puli\Manager\Api\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testInstallModuleFailsIfNoDirectory()
    {
        $this->initDefaultManager();

        $this->manager->installModule(__DIR__.'/Fixtures/file');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testInstallModuleFailsIfNoNameFound()
    {
        $this->initDefaultManager();

        $this->moduleFile3->setModuleName(null);

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->installModule($this->moduleDir3);
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\UnsupportedVersionException
     * @expectedExceptionMessage The exception text
     */
    public function testInstallModuleFailsIfModuleNotLoadableAndCustomNameSet()
    {
        $manager = new ModuleManagerImpl($this->context, $this->jsonStorage);
        $e = new UnsupportedVersionException('The exception text.');

        $this->jsonStorage->expects($this->once())
            ->method('loadModuleFile')
            ->with(Path::normalize(__DIR__).'/Fixtures/version-too-high/puli.json')
            ->willThrowException($e);

        $manager->installModule(__DIR__.'/Fixtures/version-too-high', 'vendor/my-module');
    }

    public function testRenameRootModule()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                PHPUnit_Framework_Assert::assertSame('vendor/new', $rootModuleFile->getModuleName());
            }));

        $this->assertSame('vendor/root', $this->rootModuleFile->getModuleName());
        $this->assertTrue($this->manager->hasModule('vendor/root'));

        $this->manager->renameModule('vendor/root', 'vendor/new');

        $this->assertSame('vendor/new', $this->rootModuleFile->getModuleName());
        $this->assertFalse($this->manager->hasModule('vendor/root'));
        $this->assertTrue($this->manager->hasModule('vendor/new'));

        $module = $this->manager->getModule('vendor/new');

        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModule', $module);
        $this->assertSame('vendor/new', $module->getName());
        $this->assertSame($this->rootDir, $module->getInstallPath());
    }

    public function testRenameRootModuleDoesNothingIfUnchanged()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->renameModule('vendor/root', 'vendor/root');
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\NameConflictException
     */
    public function testRenameRootModuleFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->renameModule('vendor/root', 'vendor/module1');
    }

    public function testRenameRootModuleRevertsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException());

        try {
            $this->manager->renameModule('vendor/root', 'vendor/new');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('vendor/root', $this->rootModuleFile->getModuleName());
        $this->assertTrue($this->manager->hasModule('vendor/root'));
        $this->assertFalse($this->manager->hasModule('vendor/new'));
    }

    public function testRenameNonRootModule()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                PHPUnit_Framework_Assert::assertTrue($rootModuleFile->hasInstallInfo('vendor/new'));
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasInstallInfo('vendor/module1'));
            }));

        $this->installInfo1->addDisabledBindingUuid($uuid = Uuid::uuid4());

        $this->assertSame('vendor/module1', $this->installInfo1->getModuleName());
        $this->assertTrue($this->manager->hasModule('vendor/module1'));

        $this->manager->renameModule('vendor/module1', 'vendor/new');

        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/new'));
        $this->assertFalse($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertFalse($this->manager->hasModule('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/new'));

        $module = $this->manager->getModule('vendor/new');
        $installInfo = $this->rootModuleFile->getInstallInfo('vendor/new');

        $this->assertInstanceOf('Puli\Manager\Api\Module\Module', $module);
        $this->assertSame('vendor/new', $module->getName());
        $this->assertSame($this->moduleDir1, $module->getInstallPath());
        $this->assertSame($installInfo, $module->getInstallInfo());

        $this->assertSame('vendor/new', $installInfo->getModuleName());
        $this->assertSame('../module1', $installInfo->getInstallPath());
        $this->assertSame(array($uuid), $installInfo->getDisabledBindingUuids());
    }

    public function testRenameNonRootModuleDoesNothingIfUnchanged()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->renameModule('vendor/module1', 'vendor/module1');
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\NameConflictException
     */
    public function testRenameNonRootModuleFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->renameModule('vendor/module1', 'vendor/root');
    }

    public function testRenameNonRootModuleRevertsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException());

        try {
            $this->manager->renameModule('vendor/module1', 'vendor/new');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertFalse($this->rootModuleFile->hasInstallInfo('vendor/new'));
        $this->assertTrue($this->manager->hasModule('vendor/root'));
        $this->assertFalse($this->manager->hasModule('vendor/new'));
    }

    public function testRemoveModule()
    {
        $this->initDefaultManager();

        $moduleDir = $this->moduleDir1;

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($moduleDir) {
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasInstallInfo($moduleDir));
            }));

        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/module1'));

        $this->manager->removeModule('vendor/module1');

        $this->assertFalse($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertFalse($this->manager->hasModule('vendor/module1'));
    }

    public function testRemoveModuleIgnoresUnknownName()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeModule('foobar');
    }

    public function testRemoveModuleIgnoresIfNoInstallInfoFound()
    {
        $this->initDefaultManager();

        $this->manager->getModules();

        $this->jsonStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->rootModuleFile->removeInstallInfo('vendor/module1');

        $this->assertFalse($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/module1'));

        $this->manager->removeModule('vendor/module1');

        $this->assertFalse($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertFalse($this->manager->hasModule('vendor/module1'));
    }

    public function testRemoveModuleRevertsIfSavingNotPossible()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->willThrowException(new TestException());

        try {
            $this->manager->removeModule('vendor/module1');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/module1'));
        $this->assertTrue($this->manager->getModules()->contains('vendor/module1'));
    }

    public function testRemoveModules()
    {
        $this->initDefaultManager();

        $moduleDir = $this->moduleDir1;

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($moduleDir) {
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasInstallInfo('vendor/module1'));
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasInstallInfo('vendor/module2'));
                PHPUnit_Framework_Assert::assertTrue($rootModuleFile->hasInstallInfo('vendor/module3'));
            }));

        $this->rootModuleFile->addInstallInfo($this->installInfo3);

        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module2'));
        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module3'));
        $this->assertTrue($this->manager->hasModule('vendor/root'));
        $this->assertTrue($this->manager->hasModule('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/module2'));
        $this->assertTrue($this->manager->hasModule('vendor/module3'));

        $this->manager->removeModules(Expr::method('getName', Expr::endsWith('1')->orEndsWith('2')));

        $this->assertFalse($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertFalse($this->rootModuleFile->hasInstallInfo('vendor/module2'));
        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module3'));
        $this->assertTrue($this->manager->hasModule('vendor/root'));
        $this->assertFalse($this->manager->hasModule('vendor/module1'));
        $this->assertFalse($this->manager->hasModule('vendor/module2'));
        $this->assertTrue($this->manager->hasModule('vendor/module3'));
    }

    public function testRemoveModulesRevertsIfSavingNotPossible()
    {
        $this->initDefaultManager();

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->willThrowException(new TestException());

        try {
            $this->manager->removeModules(Expr::method('getName', Expr::startsWith('vendor/module')));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module2'));
        $this->assertTrue($this->manager->hasModule('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/module2'));
        $this->assertTrue($this->manager->getModules()->contains('vendor/module1'));
        $this->assertTrue($this->manager->getModules()->contains('vendor/module2'));
    }

    public function testClearModules()
    {
        $this->initDefaultManager();

        $moduleDir = $this->moduleDir1;

        $this->jsonStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($moduleDir) {
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasInstallInfos());
            }));

        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module1'));
        $this->assertTrue($this->rootModuleFile->hasInstallInfo('vendor/module2'));
        $this->assertTrue($this->manager->hasModule('vendor/root'));
        $this->assertTrue($this->manager->hasModule('vendor/module1'));
        $this->assertTrue($this->manager->hasModule('vendor/module2'));

        $this->manager->clearModules();

        $this->assertFalse($this->rootModuleFile->hasInstallInfos());
        $this->assertCount(1, $this->manager->getModules());
        $this->assertTrue($this->manager->hasModule('vendor/root'));
    }

    public function testGetModule()
    {
        $this->initDefaultManager();

        $rootModule = $this->manager->getModule('vendor/root');

        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModule', $rootModule);
        $this->assertSame('vendor/root', $rootModule->getName());
        $this->assertSame($this->rootDir, $rootModule->getInstallPath());
        $this->assertSame($this->rootModuleFile, $rootModule->getModuleFile());

        $module1 = $this->manager->getModule('vendor/module1');

        $this->assertInstanceOf('Puli\Manager\Api\Module\Module', $module1);
        $this->assertSame('vendor/module1', $module1->getName());
        $this->assertSame($this->moduleDir1, $module1->getInstallPath());
        $this->assertSame($this->moduleFile1, $module1->getModuleFile());
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\NoSuchModuleException
     */
    public function testGetModuleFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getModule('foobar');
    }

    public function testGetRootModule()
    {
        $this->initDefaultManager();

        $rootModule = $this->manager->getRootModule();

        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModule', $rootModule);
        $this->assertSame('vendor/root', $rootModule->getName());
        $this->assertSame($this->rootDir, $rootModule->getInstallPath());
        $this->assertSame($this->rootModuleFile, $rootModule->getModuleFile());
    }

    private function initDefaultManager()
    {
        $this->rootModuleFile->addInstallInfo($this->installInfo1);
        $this->rootModuleFile->addInstallInfo($this->installInfo2);

        $this->manager = new ModuleManagerImpl($this->context, $this->jsonStorage);
    }
}
