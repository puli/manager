<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\Reader\UnsupportedVersionException;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\PackageManager;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Tests\ManagerTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManagerTest extends ManagerTestCase
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
     * @var PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var PackageManager
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/PackageManagerTest_temp'.rand(10000, 99999), 0777, true)) {}

        $this->packageDir1 = __DIR__.'/Fixtures/package1';
        $this->packageDir2 = __DIR__.'/Fixtures/package2';
        $this->packageDir3 = __DIR__.'/Fixtures/package3';

        $this->packageFile1 = new PackageFile('package1');
        $this->packageFile2 = new PackageFile('package2');
        $this->packageFile3 = new PackageFile('package3');

        $this->installInfo1 = new InstallInfo('package1', $this->packageDir1);
        $this->installInfo2 = new InstallInfo('package2', $this->packageDir2);
        $this->installInfo3 = new InstallInfo('package2', $this->packageDir3);

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->packageFileStorage->expects($this->any())
            ->method('loadPackageFile')
            ->willReturnMap(array(
                array($this->packageDir1.'/puli.json', $this->packageFile1),
                array($this->packageDir2.'/puli.json', $this->packageFile2),
                array($this->packageDir3.'/puli.json', $this->packageFile3),
            ));

        $this->logger = $this->getMock('Psr\Log\LoggerInterface');

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
        $this->rootPackageFile->addInstallInfo($installInfo1 = new InstallInfo('package1', '../package1'));
        $this->rootPackageFile->addInstallInfo($installInfo2 = new InstallInfo('package2', $this->packageDir2));

        $manager = new PackageManager($this->environment, $this->packageFileStorage, $this->logger);

        $packages = $manager->getPackages();

        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageCollection', $packages);
        $this->assertEquals(array(
            'root' => new RootPackage($this->rootPackageFile, $this->rootDir),
            'package1' => new Package($this->packageFile1, $this->packageDir1, $installInfo1),
            'package2' => new Package($this->packageFile2, $this->packageDir2, $installInfo2),
        ), $packages->toArray());
    }

    public function testLoadPackagesLogsWarningIfPackageDirectoryNotFound()
    {
        $manager = new PackageManager($this->environment, $this->packageFileStorage, $this->logger);

        $this->rootPackageFile->addInstallInfo(new InstallInfo('package', 'foobar'));

        $this->logger->expects($this->once())
            ->method('warning');

        $manager->loadPackages();

        $this->assertTrue($manager->getPackage('package')->isNotFound());
    }

    public function testLoadPackagesLogsWarningIfPackageNoDirectory()
    {
        $this->rootPackageFile->addInstallInfo(new InstallInfo('package', __DIR__.'/Fixtures/file'));

        $manager = new PackageManager($this->environment, $this->packageFileStorage, $this->logger);

        $this->logger->expects($this->once())
            ->method('warning');

        $manager->loadPackages();

        $this->assertTrue($manager->getPackage('package')->isNotLoadable());
    }

    public function testLoadPackagesLogsWarningIfPackageFileVersionNotSupported()
    {
        $this->rootPackageFile->addInstallInfo(new InstallInfo('package', $this->packageDir1));

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->willThrowException(new UnsupportedVersionException());

        $manager = new PackageManager($this->environment, $this->packageFileStorage, $this->logger);

        $manager->loadPackages();

        $this->assertTrue($manager->getPackage('package')->isNotLoadable());
    }

    public function testLoadPackagesLogsWarningIfPackageFileInvalid()
    {
        $this->rootPackageFile->addInstallInfo(new InstallInfo('package', $this->packageDir1));

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->willThrowException(new InvalidConfigException());

        $manager = new PackageManager($this->environment, $this->packageFileStorage, $this->logger);

        $manager->loadPackages();

        $this->assertTrue($manager->getPackage('package')->isNotLoadable());
    }

    public function testInstallPackage()
    {
        $this->initDefaultManager();

        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installPackage($this->packageDir3);
    }

    public function testInstallPackageWithRelativePath()
    {
        $this->initDefaultManager();

        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installPackage('../package3');
    }

    public function testInstallPackageWithCustomName()
    {
        $this->initDefaultManager();

        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('package1', $installInfos[0]->getPackageName());
                PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('package2', $installInfos[1]->getPackageName());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('package3-custom', $installInfos[2]->getPackageName());
            }));

        $this->manager->installPackage($this->packageDir3, 'package3-custom');
    }

    public function testInstallPackageWithCustomInstaller()
    {
        $this->initDefaultManager();

        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('User', $installInfos[0]->getInstaller());
                PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('User', $installInfos[1]->getInstaller());
                PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
                PHPUnit_Framework_Assert::assertSame('Composer', $installInfos[2]->getInstaller());
            }));

        $this->manager->installPackage($this->packageDir3, null, 'Composer');
    }

    public function testInstallPackageDoesNothingIfAlreadyInstalled()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->installPackage($this->packageDir2);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NameConflictException
     */
    public function testInstallPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->packageFile3->setPackageName('package2');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->installPackage($this->packageDir3);
    }

    /**
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testInstallPackageFailsIfDirectoryNotFound()
    {
        $this->initDefaultManager();

        $this->manager->installPackage(__DIR__.'/foobar');
    }

    /**
     * @expectedException \Puli\RepositoryManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testInstallPackageFailsIfNoDirectory()
    {
        $this->initDefaultManager();

        $this->manager->installPackage(__DIR__.'/Fixtures/file');
    }

    /**
     * @expectedException \Puli\RepositoryManager\InvalidConfigException
     */
    public function testInstallPackageFailsIfNoNameFound()
    {
        $this->initDefaultManager();

        $this->packageFile3->setPackageName(null);

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->installPackage($this->packageDir3);
    }

    public function testIsPackageInstalled()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->isPackageInstalled($this->packageDir1));
        $this->assertFalse($this->manager->isPackageInstalled($this->packageDir3));
    }

    public function testIsPackageInstalledAcceptsRelativePath()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->isPackageInstalled('../package1'));
        $this->assertFalse($this->manager->isPackageInstalled('../package3'));
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

        $this->assertTrue($this->rootPackageFile->hasInstallInfo('package1'));
        $this->assertTrue($this->manager->hasPackage('package1'));

        $this->manager->removePackage('package1');

        $this->assertFalse($this->rootPackageFile->hasInstallInfo('package1'));
        $this->assertFalse($this->manager->hasPackage('package1'));
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

        $this->manager->loadPackages();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->rootPackageFile->removeInstallInfo('package1');

        $this->assertFalse($this->rootPackageFile->hasInstallInfo('package1'));
        $this->assertTrue($this->manager->hasPackage('package1'));

        $this->manager->removePackage('package1');

        $this->assertFalse($this->rootPackageFile->hasInstallInfo('package1'));
        $this->assertFalse($this->manager->hasPackage('package1'));
    }

    public function testHasPackage()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->hasPackage('root'));
        $this->assertTrue($this->manager->hasPackage('package1'));
        $this->assertTrue($this->manager->hasPackage('package2'));
        $this->assertFalse($this->manager->hasPackage('package3'));
    }

    public function testGetPackage()
    {
        $this->initDefaultManager();

        $rootPackage = $this->manager->getPackage('root');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\RootPackage', $rootPackage);
        $this->assertSame('root', $rootPackage->getName());
        $this->assertSame($this->rootDir, $rootPackage->getInstallPath());
        $this->assertSame($this->rootPackageFile, $rootPackage->getPackageFile());

        $package1 = $this->manager->getPackage('package1');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $package1);
        $this->assertSame('package1', $package1->getName());
        $this->assertSame($this->packageDir1, $package1->getInstallPath());
        $this->assertSame($this->packageFile1, $package1->getPackageFile());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
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

        $this->assertInstanceOf('Puli\RepositoryManager\Package\RootPackage', $rootPackage);
        $this->assertSame('root', $rootPackage->getName());
        $this->assertSame($this->rootDir, $rootPackage->getInstallPath());
        $this->assertSame($this->rootPackageFile, $rootPackage->getPackageFile());
    }

    public function testGetPackagesByInstaller()
    {
        $this->initDefaultManager();

        $installInfo1 = new InstallInfo('package1', $this->packageDir1);
        $installInfo1->setInstaller('Composer');
        $installInfo2 = new InstallInfo('package2', $this->packageDir2);
        $installInfo2->setInstaller('User');
        $installInfo3 = new InstallInfo('package3', $this->packageDir3);
        $installInfo3->setInstaller('Composer');

        $this->rootPackageFile->addInstallInfo($installInfo1);
        $this->rootPackageFile->addInstallInfo($installInfo2);
        $this->rootPackageFile->addInstallInfo($installInfo3);

        $this->manager = new PackageManager($this->environment, $this->packageFileStorage, $this->logger);

        $composerPackages = $this->manager->getPackagesByInstaller('Composer');
        $userPackages = $this->manager->getPackagesByInstaller('User');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageCollection', $composerPackages);
        $this->assertCount(2, $composerPackages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $composerPackages['package1']);
        $this->assertSame('package1', $composerPackages['package1']->getName());
        $this->assertSame($installInfo1, $composerPackages['package1']->getInstallInfo());
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $composerPackages['package3']);
        $this->assertSame('package3', $composerPackages['package3']->getName());
        $this->assertSame($installInfo3, $composerPackages['package3']->getInstallInfo());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageCollection', $userPackages);
        $this->assertCount(1, $userPackages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $userPackages['package2']);
        $this->assertSame('package2', $userPackages['package2']->getName());
        $this->assertSame($installInfo2, $userPackages['package2']->getInstallInfo());
    }

    private function initDefaultManager()
    {
        $this->rootPackageFile->addInstallInfo($this->installInfo1);
        $this->rootPackageFile->addInstallInfo($this->installInfo2);

        $this->manager = new PackageManager($this->environment, $this->packageFileStorage, $this->logger);
    }
}
