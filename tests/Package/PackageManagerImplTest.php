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

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageState;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\UnsupportedVersionException;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Package\PackageManagerImpl;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Manager\Tests\TestException;
use Rhumsaa\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManagerImplTest extends ManagerTestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $packageDir1;

    /**
     * @var string
     */
    private $packageDir2;

    /**
     * @var string
     */
    private $packageDir3;

    /**
     * @var PackageFile
     */
    private $packageFile1;

    /**
     * @var PackageFile
     */
    private $packageFile2;

    /**
     * @var PackageFile
     */
    private $packageFile3;

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
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PackageManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/PackageManagerTest_temp'.rand(10000, 99999), 0777, true)) {
        }

        $this->packageDir1 = __DIR__.'/Fixtures/package1';
        $this->packageDir2 = __DIR__.'/Fixtures/package2';
        $this->packageDir3 = __DIR__.'/Fixtures/package3';

        $this->packageFile1 = new PackageFile();
        $this->packageFile2 = new PackageFile();
        $this->packageFile3 = new PackageFile('vendor/package3');

        $this->installInfo1 = new InstallInfo('vendor/package1', '../package1');
        $this->installInfo2 = new InstallInfo('vendor/package2', '../package2');
        $this->installInfo3 = new InstallInfo('vendor/package3', '../package3');

        $this->packageFileStorage = $this->getMockBuilder('Puli\Manager\Package\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->packageFileStorage->expects($this->any())
            ->method('loadPackageFile')
            ->willReturnMap(array(
                array($this->packageDir1.'/puli.json', $this->packageFile1),
                array($this->packageDir2.'/puli.json', $this->packageFile2),
                array($this->packageDir3.'/puli.json', $this->packageFile3),
            ));

        $this->initEnvironment(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');
    }

    protected function tearDown()
    {
        // Make sure initDefaultManager() is called again
        $this->manager = null;

        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testGetPackages()
    {
        $this->rootPackageFile->addInstallInfo($installInfo1 = new InstallInfo('vendor/package1', '../foo'));
        $this->rootPackageFile->addInstallInfo($installInfo2 = new InstallInfo('vendor/package2', $this->packageDir2));

        $manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);

        $packages = $manager->getPackages();

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageCollection', $packages);
        $this->assertTrue($packages->contains('vendor/root'));
        $this->assertTrue($packages->contains('vendor/package1'));
        $this->assertTrue($packages->contains('vendor/package2'));
        $this->assertCount(3, $packages);
    }

    public function testFindPackages()
    {
        $this->rootPackageFile->addInstallInfo($installInfo1 = new InstallInfo('vendor/package1', '../foo'));
        $this->rootPackageFile->addInstallInfo($installInfo2 = new InstallInfo('vendor/package2', $this->packageDir2));

        $installInfo1->setInstallerName('webmozart');

        $manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);

        $expr1 = Expr::same(PackageState::ENABLED, Package::STATE);

        $expr2 = Expr::same('webmozart', Package::INSTALLER);

        $expr3 = $expr1->andX($expr2);

        $packages = $manager->findPackages($expr1);

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageCollection', $packages);
        $this->assertTrue($packages->contains('vendor/root'));
        $this->assertTrue($packages->contains('vendor/package2'));
        $this->assertCount(2, $packages);

        $packages = $manager->findPackages($expr2);

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageCollection', $packages);
        $this->assertTrue($packages->contains('vendor/package1'));
        $this->assertCount(1, $packages);

        $packages = $manager->findPackages($expr3);

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageCollection', $packages);
        $this->assertCount(0, $packages);
    }

    public function testGetPackagesStoresExceptionIfPackageDirectoryNotFound()
    {
        $manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);

        $this->rootPackageFile->addInstallInfo(new InstallInfo('vendor/package', 'foobar'));

        $packages = $manager->getPackages();

        $this->assertTrue($packages['vendor/package']->isNotFound());

        $loadErrors = $packages['vendor/package']->getLoadErrors();

        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\Manager\Api\FileNotFoundException', $loadErrors[0]);
    }

    public function testGetPackagesStoresExceptionIfPackageNoDirectory()
    {
        $this->rootPackageFile->addInstallInfo(new InstallInfo('vendor/package', __DIR__.'/Fixtures/file'));

        $manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);

        $packages = $manager->getPackages();

        $this->assertTrue($packages['vendor/package']->isNotLoadable());

        $loadErrors = $packages['vendor/package']->getLoadErrors();

        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\Manager\Api\NoDirectoryException', $loadErrors[0]);
    }

    public function testGetPackagesStoresExceptionIfPackageFileVersionNotSupported()
    {
        $this->rootPackageFile->addInstallInfo(new InstallInfo('vendor/package', $this->packageDir1));

        $exception = new UnsupportedVersionException();

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->willThrowException($exception);

        $manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);

        $packages = $manager->getPackages();

        $this->assertTrue($packages['vendor/package']->isNotLoadable());
        $this->assertSame(array($exception), $packages['vendor/package']->getLoadErrors());
    }

    public function testGetPackagesStoresExceptionIfPackageFileInvalid()
    {
        $this->rootPackageFile->addInstallInfo(new InstallInfo('vendor/package', $this->packageDir1));

        $exception = new InvalidConfigException();

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->willThrowException($exception);

        $manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);

        $packages = $manager->getPackages();

        $this->assertTrue($packages['vendor/package']->isNotLoadable());
        $this->assertSame(array($exception), $packages['vendor/package']->getLoadErrors());
    }

    public function testHasPackage()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->hasPackage('vendor/root'));
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/package2'));
        $this->assertFalse($this->manager->hasPackage('vendor/package3'));
    }

    public function testHasPackages()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->hasPackages());
        $this->assertTrue($this->manager->hasPackages(Expr::same('vendor/root', Package::NAME)));
        $this->assertFalse($this->manager->hasPackages(Expr::same('foobar', Package::NAME)));
    }

    public function testInstallPackage()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../package1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../package2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installPackage($this->packageDir3);
    }

    public function testInstallPackageWithRelativePath()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../package1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../package2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installPackage('../package3');
    }

    public function testInstallPackageWithCustomName()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../package1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('vendor/package1', $installInfos[0]->getPackageName());
                PHPUnit_Framework_Assert::assertSame('../package2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('vendor/package2', $installInfos[1]->getPackageName());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('my/package3-custom', $installInfos[2]->getPackageName());
            }));

        $this->manager->installPackage($this->packageDir3, 'my/package3-custom');
    }

    public function testInstallDevPackage()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../package1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertFalse($installInfos[0]->isDevDependency());
                PHPUnit_Framework_Assert::assertSame('../package2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertFalse($installInfos[1]->isDevDependency());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertTrue($installInfos[2]->isDevDependency());
            }));

        $this->manager->installPackage($this->packageDir3, null, InstallInfo::DEFAULT_INSTALLER_NAME, true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage package3
     */
    public function testInstallPackageFailsIfNoVendorPrefix()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->installPackage($this->packageDir3, 'package3');
    }

    public function testInstallPackageWithCustomInstaller()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame('../package1', $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('user', $installInfos[0]->getInstallerName());
                PHPUnit_Framework_Assert::assertSame('../package2', $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('user', $installInfos[1]->getInstallerName());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('composer', $installInfos[2]->getInstallerName());
            }));

        $this->manager->installPackage($this->packageDir3, null, 'composer');
    }

    public function testInstallPackageDoesNothingIfAlreadyInstalled()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->installPackage($this->packageDir2);
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NameConflictException
     */
    public function testInstallPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->packageFile3->setPackageName('vendor/package2');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->installPackage($this->packageDir3);
    }

    /**
     * @expectedException \Puli\Manager\Api\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testInstallPackageFailsIfDirectoryNotFound()
    {
        $this->initDefaultManager();

        $this->manager->installPackage(__DIR__.'/foobar');
    }

    /**
     * @expectedException \Puli\Manager\Api\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testInstallPackageFailsIfNoDirectory()
    {
        $this->initDefaultManager();

        $this->manager->installPackage(__DIR__.'/Fixtures/file');
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testInstallPackageFailsIfNoNameFound()
    {
        $this->initDefaultManager();

        $this->packageFile3->setPackageName(null);

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->installPackage($this->packageDir3);
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\UnsupportedVersionException
     * @expectedExceptionMessage The exception text
     */
    public function testInstallPackageFailsIfPackageNotLoadableAndCustomNameSet()
    {
        $manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);
        $e = new UnsupportedVersionException('The exception text.');

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with(__DIR__.'/Fixtures/version-too-high/puli.json')
            ->willThrowException($e);

        $manager->installPackage(__DIR__.'/Fixtures/version-too-high', 'vendor/my-package');
    }

    public function testRenameRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                PHPUnit_Framework_Assert::assertSame('vendor/new', $rootPackageFile->getPackageName());
            }));

        $this->assertSame('vendor/root', $this->rootPackageFile->getPackageName());
        $this->assertTrue($this->manager->hasPackage('vendor/root'));

        $this->manager->renamePackage('vendor/root', 'vendor/new');

        $this->assertSame('vendor/new', $this->rootPackageFile->getPackageName());
        $this->assertFalse($this->manager->hasPackage('vendor/root'));
        $this->assertTrue($this->manager->hasPackage('vendor/new'));

        $package = $this->manager->getPackage('vendor/new');

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackage', $package);
        $this->assertSame('vendor/new', $package->getName());
        $this->assertSame($this->rootDir, $package->getInstallPath());
    }

    public function testRenameRootPackageDoesNothingIfUnchanged()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->renamePackage('vendor/root', 'vendor/root');
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NameConflictException
     */
    public function testRenameRootPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->renamePackage('vendor/root', 'vendor/package1');
    }

    public function testRenameRootPackageRevertsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException());

        try {
            $this->manager->renamePackage('vendor/root', 'vendor/new');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame('vendor/root', $this->rootPackageFile->getPackageName());
        $this->assertTrue($this->manager->hasPackage('vendor/root'));
        $this->assertFalse($this->manager->hasPackage('vendor/new'));
    }

    public function testRenameNonRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                PHPUnit_Framework_Assert::assertTrue($rootPackageFile->hasInstallInfo('vendor/new'));
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasInstallInfo('vendor/package1'));
            }));

        $this->installInfo1->addEnabledBindingUuid($uuid1 = Uuid::uuid4());
        $this->installInfo1->addDisabledBindingUuid($uuid2 = Uuid::uuid4());

        $this->assertSame('vendor/package1', $this->installInfo1->getPackageName());
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));

        $this->manager->renamePackage('vendor/package1', 'vendor/new');

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/new'));
        $this->assertFalse($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertFalse($this->manager->hasPackage('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/new'));

        $package = $this->manager->getPackage('vendor/new');
        $installInfo = $this->rootPackageFile->getInstallInfo('vendor/new');

        $this->assertInstanceOf('Puli\Manager\Api\Package\Package', $package);
        $this->assertSame('vendor/new', $package->getName());
        $this->assertSame($this->packageDir1, $package->getInstallPath());
        $this->assertSame($installInfo, $package->getInstallInfo());

        $this->assertSame('vendor/new', $installInfo->getPackageName());
        $this->assertSame('../package1', $installInfo->getInstallPath());
        $this->assertSame(array($uuid1), $installInfo->getEnabledBindingUuids());
        $this->assertSame(array($uuid2), $installInfo->getDisabledBindingUuids());
    }

    public function testRenameNonRootPackageDoesNothingIfUnchanged()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->renamePackage('vendor/package1', 'vendor/package1');
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NameConflictException
     */
    public function testRenameNonRootPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->renamePackage('vendor/package1', 'vendor/root');
    }

    public function testRenameNonRootPackageRevertsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException());

        try {
            $this->manager->renamePackage('vendor/package1', 'vendor/new');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertFalse($this->rootPackageFile->hasInstallInfo('vendor/new'));
        $this->assertTrue($this->manager->hasPackage('vendor/root'));
        $this->assertFalse($this->manager->hasPackage('vendor/new'));
    }

    public function testRemovePackage()
    {
        $this->initDefaultManager();

        $packageDir = $this->packageDir1;

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir) {
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasInstallInfo($packageDir));
            }));

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));

        $this->manager->removePackage('vendor/package1');

        $this->assertFalse($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertFalse($this->manager->hasPackage('vendor/package1'));
    }

    public function testRemovePackageIgnoresUnknownName()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removePackage('foobar');
    }

    public function testRemovePackageIgnoresIfNoInstallInfoFound()
    {
        $this->initDefaultManager();

        $this->manager->getPackages();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->rootPackageFile->removeInstallInfo('vendor/package1');

        $this->assertFalse($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));

        $this->manager->removePackage('vendor/package1');

        $this->assertFalse($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertFalse($this->manager->hasPackage('vendor/package1'));
    }

    public function testRemovePackageRevertsIfSavingNotPossible()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException());

        try {
            $this->manager->removePackage('vendor/package1');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));
        $this->assertTrue($this->manager->getPackages()->contains('vendor/package1'));
    }

    public function testRemovePackages()
    {
        $this->initDefaultManager();

        $packageDir = $this->packageDir1;

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir) {
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasInstallInfo('vendor/package1'));
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasInstallInfo('vendor/package2'));
                PHPUnit_Framework_Assert::assertTrue($rootPackageFile->hasInstallInfo('vendor/package3'));
            }));

        $this->rootPackageFile->addInstallInfo($this->installInfo3);

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package2'));
        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package3'));
        $this->assertTrue($this->manager->hasPackage('vendor/root'));
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/package2'));
        $this->assertTrue($this->manager->hasPackage('vendor/package3'));

        $this->manager->removePackages(Expr::key(Package::NAME, Expr::endsWith('1')->orEndsWith('2')));

        $this->assertFalse($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertFalse($this->rootPackageFile->hasInstallInfo('vendor/package2'));
        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package3'));
        $this->assertTrue($this->manager->hasPackage('vendor/root'));
        $this->assertFalse($this->manager->hasPackage('vendor/package1'));
        $this->assertFalse($this->manager->hasPackage('vendor/package2'));
        $this->assertTrue($this->manager->hasPackage('vendor/package3'));
    }

    public function testRemovePackagesRevertsIfSavingNotPossible()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException());

        try {
            $this->manager->removePackages(Expr::startsWith('vendor/package', Package::NAME));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package2'));
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/package2'));
        $this->assertTrue($this->manager->getPackages()->contains('vendor/package1'));
        $this->assertTrue($this->manager->getPackages()->contains('vendor/package2'));
    }

    public function testClearPackages()
    {
        $this->initDefaultManager();

        $packageDir = $this->packageDir1;

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir) {
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasInstallInfos());
            }));

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package1'));
        $this->assertTrue($this->rootPackageFile->hasInstallInfo('vendor/package2'));
        $this->assertTrue($this->manager->hasPackage('vendor/root'));
        $this->assertTrue($this->manager->hasPackage('vendor/package1'));
        $this->assertTrue($this->manager->hasPackage('vendor/package2'));

        $this->manager->clearPackages();

        $this->assertFalse($this->rootPackageFile->hasInstallInfos());
        $this->assertCount(1, $this->manager->getPackages());
        $this->assertTrue($this->manager->hasPackage('vendor/root'));
    }

    public function testGetPackage()
    {
        $this->initDefaultManager();

        $rootPackage = $this->manager->getPackage('vendor/root');

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackage', $rootPackage);
        $this->assertSame('vendor/root', $rootPackage->getName());
        $this->assertSame($this->rootDir, $rootPackage->getInstallPath());
        $this->assertSame($this->rootPackageFile, $rootPackage->getPackageFile());

        $package1 = $this->manager->getPackage('vendor/package1');

        $this->assertInstanceOf('Puli\Manager\Api\Package\Package', $package1);
        $this->assertSame('vendor/package1', $package1->getName());
        $this->assertSame($this->packageDir1, $package1->getInstallPath());
        $this->assertSame($this->packageFile1, $package1->getPackageFile());
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NoSuchPackageException
     */
    public function testGetPackageFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getPackage('foobar');
    }

    public function testGetRootPackage()
    {
        $this->initDefaultManager();

        $rootPackage = $this->manager->getRootPackage();

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackage', $rootPackage);
        $this->assertSame('vendor/root', $rootPackage->getName());
        $this->assertSame($this->rootDir, $rootPackage->getInstallPath());
        $this->assertSame($this->rootPackageFile, $rootPackage->getPackageFile());
    }

    private function initDefaultManager()
    {
        $this->rootPackageFile->addInstallInfo($this->installInfo1);
        $this->rootPackageFile->addInstallInfo($this->installInfo2);

        $this->manager = new PackageManagerImpl($this->environment, $this->packageFileStorage);
    }
}
