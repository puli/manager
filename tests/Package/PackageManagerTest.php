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

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\PackageManager;
use Puli\RepositoryManager\Tests\Package\Fixtures\TestProjectEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var string
     */
    private $rootDir;

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
     * @var ConfigFile
     */
    private $configFile;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

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
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var TestProjectEnvironment
     */
    private $environment;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PackageManager
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/PackageManagerTest_temp'.rand(10000, 99999), 0777, true)) {}

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->rootDir = __DIR__.'/Fixtures/root';
        $this->packageDir1 = __DIR__.'/Fixtures/package1';
        $this->packageDir2 = __DIR__.'/Fixtures/package2';
        $this->packageDir3 = __DIR__.'/Fixtures/package3';

        $this->configFile = new ConfigFile();
        $this->rootPackageFile = new RootPackageFile('root');
        $this->packageFile1 = new PackageFile('package1');
        $this->packageFile2 = new PackageFile('package2');
        $this->packageFile3 = new PackageFile('package3');

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->initEnvironment();
    }

    protected function tearDown()
    {
        // Make sure initDefaultManager() is called again
        $this->manager = null;

        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testLoadPackages()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $packageFile1 = new PackageFile();
        $packageFile2 = new PackageFile();

        $this->rootPackageFile->addInstallInfo(new InstallInfo('package1', '../package1'));
        $this->rootPackageFile->addInstallInfo(new InstallInfo('package2', realpath($this->rootDir.'/../package2')));

        $this->packageFileStorage->expects($this->at(0))
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->will($this->returnValue($packageFile1));
        $this->packageFileStorage->expects($this->at(1))
            ->method('loadPackageFile')
            ->with($this->packageDir2.'/puli.json')
            ->will($this->returnValue($packageFile2));

        $manager = new PackageManager($this->environment, $this->packageFileStorage);

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\RootPackage', $packages['root']);
        $this->assertSame('root', $packages['root']->getName());
        $this->assertSame($this->rootDir, $packages['root']->getInstallPath());
        $this->assertSame($this->rootPackageFile, $packages['root']->getPackageFile());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $packages['package1']);
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame($this->packageDir1, $packages['package1']->getInstallPath());
        $this->assertSame($packageFile1, $packages['package1']->getPackageFile());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $packages['package2']);
        $this->assertSame('package2', $packages['package2']->getName());
        $this->assertSame($this->packageDir2, $packages['package2']->getInstallPath());
        $this->assertSame($packageFile2, $packages['package2']->getPackageFile());
    }

    public function testLoadPackagesPrefersNameGivenDuringInstall()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $packageFile = new PackageFile('package1');
        $installInfo = new InstallInfo('package1-custom', '../package1');

        $this->rootPackageFile->addInstallInfo($installInfo);

        $this->packageFileStorage->expects($this->at(0))
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->will($this->returnValue($packageFile));

        $manager = new PackageManager($this->environment, $this->packageFileStorage);

        $packages = $manager->getPackages();

        $this->assertCount(2, $packages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\RootPackage', $packages['root']);
        $this->assertSame('root', $packages['root']->getName());
        $this->assertSame($this->rootDir, $packages['root']->getInstallPath());
        $this->assertSame($this->rootPackageFile, $packages['root']->getPackageFile());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $packages['package1-custom']);
        $this->assertSame('package1-custom', $packages['package1-custom']->getName());
        $this->assertSame($this->packageDir1, $packages['package1-custom']->getInstallPath());
        $this->assertSame($packageFile, $packages['package1-custom']->getPackageFile());
    }

    /**
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage foobar
     */
    public function testLoadPackagesFailsIfPackageDirNotFound()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $this->rootPackageFile->addInstallInfo(new InstallInfo('package', 'foobar'));

        new PackageManager($this->environment, $this->packageFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testLoadPackagesFailsIfPackageNoDirectory()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $this->rootPackageFile->addInstallInfo(new InstallInfo('package', __DIR__.'/Fixtures/file'));

        new PackageManager($this->environment, $this->packageFileStorage);
    }

    public function testInstallPackage()
    {
        $this->initDefaultManager();

        $packageFile = new PackageFile('package3');
        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->exactly(2))
            ->method('loadPackageFile')
            ->with($this->packageDir3.'/puli.json')
            ->will($this->returnValue($packageFile));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                \PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                \PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installPackage($this->packageDir3);
    }

    public function testInstallPackageWithRelativePath()
    {
        $this->initDefaultManager();

        $packageFile = new PackageFile('package3');
        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->exactly(2))
            ->method('loadPackageFile')
            ->with($this->packageDir3.'/puli.json')
            ->will($this->returnValue($packageFile));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                \PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                \PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
            }));

        $this->manager->installPackage('../package3');
    }

    public function testInstallPackageWithCustomName()
    {
        $this->initDefaultManager();

        $packageFile = new PackageFile('package3');
        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir3.'/puli.json')
            ->will($this->returnValue($packageFile));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                \PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                \PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('package1', $installInfos[0]->getPackageName());
                \PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('package2', $installInfos[1]->getPackageName());
                \PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('package3-custom', $installInfos[2]->getPackageName());
            }));

        $this->manager->installPackage($this->packageDir3, 'package3-custom');
    }

    public function testInstallPackageWithCustomInstaller()
    {
        $this->initDefaultManager();

        $packageFile = new PackageFile('package3');
        $packageDir1 = $this->packageDir1;
        $packageDir2 = $this->packageDir2;

        $this->packageFileStorage->expects($this->exactly(2))
            ->method('loadPackageFile')
            ->with($this->packageDir3.'/puli.json')
            ->will($this->returnValue($packageFile));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($packageDir1, $packageDir2) {
                $installInfos = $rootPackageFile->getInstallInfos();

                \PHPUnit_Framework_Assert::assertCount(3, $installInfos);
                \PHPUnit_Framework_Assert::assertSame($packageDir1, $installInfos[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('User', $installInfos[0]->getInstaller());
                \PHPUnit_Framework_Assert::assertSame($packageDir2, $installInfos[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('User', $installInfos[1]->getInstaller());
                \PHPUnit_Framework_Assert::assertSame('../package3', $installInfos[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertSame('Composer', $installInfos[2]->getInstaller());
            }));

        $this->manager->installPackage($this->packageDir3, null, 'Composer');
    }

    public function testInstallPackageDoesNothingIfAlreadyInstalled()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('loadPackageFile');

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
                \PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasInstallInfo($packageDir));
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

        $this->manager = new PackageManager($this->environment, $this->packageFileStorage);

        $composerPackages = $this->manager->getPackagesByInstaller('Composer');
        $userPackages = $this->manager->getPackagesByInstaller('User');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Collection\PackageCollection', $composerPackages);
        $this->assertCount(2, $composerPackages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $composerPackages['package1']);
        $this->assertSame('package1', $composerPackages['package1']->getName());
        $this->assertSame($installInfo1, $composerPackages['package1']->getInstallInfo());
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $composerPackages['package3']);
        $this->assertSame('package3', $composerPackages['package3']->getName());
        $this->assertSame($installInfo3, $composerPackages['package3']->getInstallInfo());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Collection\PackageCollection', $userPackages);
        $this->assertCount(1, $userPackages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $userPackages['package2']);
        $this->assertSame('package2', $userPackages['package2']->getName());
        $this->assertSame($installInfo2, $userPackages['package2']->getInstallInfo());
    }

    private function initEnvironment()
    {
        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFile,
            $this->rootPackageFile,
            $this->dispatcher
        );
    }

    private function initDefaultManager()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $installInfo1 = new InstallInfo('package1', $this->packageDir1);
        $installInfo2 = new InstallInfo('package2', $this->packageDir2);

        $this->rootPackageFile->addInstallInfo($installInfo1);
        $this->rootPackageFile->addInstallInfo($installInfo2);

        $packageFiles = array(
            $this->packageDir1.'/puli.json' => $this->packageFile1,
            $this->packageDir2.'/puli.json' => $this->packageFile2,
            $this->packageDir3.'/puli.json' => $this->packageFile3,
        );

        $this->packageFileStorage->expects($this->any())
            ->method('loadPackageFile')
            ->will($this->returnCallback(function ($path) use ($packageFiles) {
                return $packageFiles[$path];
            }));

        $this->manager = new PackageManager($this->environment, $this->packageFileStorage);
    }
}
