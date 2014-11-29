<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Puli\RepositoryManager\Package\InstallFile\InstallFileStorage;
use Puli\RepositoryManager\Package\InstallFile\PackageMetadata;
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
     * @var InstallFile
     */
    private $installFile;

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
     * @var \PHPUnit_Framework_MockObject_MockObject|InstallFileStorage
     */
    private $installFileStorage;

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
        $this->installFile = new InstallFile();

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->installFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\InstallFile\InstallFileStorage')
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

        $package1Config = new PackageFile('package1');
        $package2Config = new PackageFile('package2');

        $installFile = new InstallFile();
        $installFile->addPackageMetadata(new PackageMetadata('../package1'));
        $installFile->addPackageMetadata(new PackageMetadata(realpath($this->rootDir.'/../package2')));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        $this->packageFileStorage->expects($this->at(0))
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->will($this->returnValue($package1Config));
        $this->packageFileStorage->expects($this->at(1))
            ->method('loadPackageFile')
            ->with($this->packageDir2.'/puli.json')
            ->will($this->returnValue($package2Config));

        $manager = new PackageManager($this->environment, $this->packageFileStorage, $this->installFileStorage);

        $this->assertSame($installFile, $manager->getInstallFile());

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\RootPackage', $packages['root']);
        $this->assertSame('root', $packages['root']->getName());
        $this->assertSame($this->rootDir, $packages['root']->getInstallPath());
        $this->assertSame($this->rootPackageFile, $packages['root']->getPackageFile());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $packages['package1']);
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame($this->packageDir1, $packages['package1']->getInstallPath());
        $this->assertSame($package1Config, $packages['package1']->getPackageFile());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $packages['package2']);
        $this->assertSame('package2', $packages['package2']->getName());
        $this->assertSame($this->packageDir2, $packages['package2']->getInstallPath());
        $this->assertSame($package2Config, $packages['package2']->getPackageFile());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NameConflictException
     */
    public function testLoadPackagesFailsIfNameConflict()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $package1Config = new PackageFile('package1');
        $package2Config = new PackageFile('package1');

        $installFile = new InstallFile();
        $installFile->addPackageMetadata(new PackageMetadata($this->packageDir1));
        $installFile->addPackageMetadata(new PackageMetadata($this->packageDir2));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        $this->packageFileStorage->expects($this->at(0))
            ->method('loadPackageFile')
            ->with($this->packageDir1.'/puli.json')
            ->will($this->returnValue($package1Config));
        $this->packageFileStorage->expects($this->at(1))
            ->method('loadPackageFile')
            ->with($this->packageDir2.'/puli.json')
            ->will($this->returnValue($package2Config));

        new PackageManager($this->environment, $this->packageFileStorage, $this->installFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage foobar
     */
    public function testLoadPackagesFailsIfPackageDirNotFound()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $installFile = new InstallFile();
        $installFile->addPackageMetadata(new PackageMetadata('foobar'));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        new PackageManager($this->environment, $this->packageFileStorage, $this->installFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testLoadPackagesFailsIfPackageNoDirectory()
    {
        $this->environment->getConfig()->set(Config::INSTALL_FILE, 'repository.json');

        $installFile = new InstallFile();
        $installFile->addPackageMetadata(new PackageMetadata(__DIR__.'/Fixtures/file'));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        new PackageManager($this->environment, $this->packageFileStorage, $this->installFileStorage);
    }

    public function testInstallPackage()
    {
        $this->initDefaultManager();

        $config = new PackageFile('package3');

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir3.'/puli.json')
            ->will($this->returnValue($config));

        $this->installFileStorage->expects($this->once())
            ->method('saveInstallFile')
            ->with($this->isInstanceOf('Puli\RepositoryManager\Package\InstallFile\InstallFile'))
            ->will($this->returnCallback(function (InstallFile $installFile) {
                $metadata = $installFile->listPackageMetadata();

                \PHPUnit_Framework_Assert::assertCount(3, $metadata);
                \PHPUnit_Framework_Assert::assertSame($this->packageDir1, $metadata[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($metadata[0]->isNew());
                \PHPUnit_Framework_Assert::assertSame($this->packageDir2, $metadata[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($metadata[1]->isNew());
                \PHPUnit_Framework_Assert::assertSame('../package3', $metadata[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertTrue($metadata[2]->isNew());
            }));

        $this->manager->installPackage($this->packageDir3);
    }

    public function testInstallPackageWithRelativePath()
    {
        $this->initDefaultManager();

        $config = new PackageFile('package3');

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir3.'/puli.json')
            ->will($this->returnValue($config));

        $this->installFileStorage->expects($this->once())
            ->method('saveInstallFile')
            ->with($this->isInstanceOf('Puli\RepositoryManager\Package\InstallFile\InstallFile'))
            ->will($this->returnCallback(function (InstallFile $installFile) {
                $metadata = $installFile->listPackageMetadata();

                \PHPUnit_Framework_Assert::assertCount(3, $metadata);
                \PHPUnit_Framework_Assert::assertSame($this->packageDir1, $metadata[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($metadata[0]->isNew());
                \PHPUnit_Framework_Assert::assertSame($this->packageDir2, $metadata[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($metadata[1]->isNew());
                \PHPUnit_Framework_Assert::assertSame('../package3', $metadata[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertTrue($metadata[2]->isNew());
            }));

        $this->manager->installPackage('../package3');
    }

    public function testInstallPackageWithCustomInstaller()
    {
        $this->initDefaultManager();

        $config = new PackageFile('package3');

        $this->packageFileStorage->expects($this->once())
            ->method('loadPackageFile')
            ->with($this->packageDir3.'/puli.json')
            ->will($this->returnValue($config));

        $this->installFileStorage->expects($this->once())
            ->method('saveInstallFile')
            ->with($this->isInstanceOf('Puli\RepositoryManager\Package\InstallFile\InstallFile'))
            ->will($this->returnCallback(function (InstallFile $installFile) {
                $metadata = $installFile->listPackageMetadata();

                \PHPUnit_Framework_Assert::assertCount(3, $metadata);
                \PHPUnit_Framework_Assert::assertSame($this->packageDir1, $metadata[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($metadata[0]->isNew());
                \PHPUnit_Framework_Assert::assertSame('User', $metadata[0]->getInstaller());
                \PHPUnit_Framework_Assert::assertSame($this->packageDir2, $metadata[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($metadata[1]->isNew());
                \PHPUnit_Framework_Assert::assertSame('User', $metadata[1]->getInstaller());
                \PHPUnit_Framework_Assert::assertSame('../package3', $metadata[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertTrue($metadata[2]->isNew());
                \PHPUnit_Framework_Assert::assertSame('Composer', $metadata[2]->getInstaller());
            }));

        $this->manager->installPackage($this->packageDir3, 'Composer');
    }

    public function testInstallPackageDoesNothingIfAlreadyInstalled()
    {
        $this->initDefaultManager();

        $this->packageFileStorage->expects($this->never())
            ->method('loadPackageFile');

        $this->installFileStorage->expects($this->never())
            ->method('saveInstallFile');

        $this->manager->installPackage($this->packageDir2);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NameConflictException
     */
    public function testInstallPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $this->packageFile3->setPackageName('package2');

        $this->installFileStorage->expects($this->never())
            ->method('saveInstallFile');

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

        $this->installFileStorage->expects($this->once())
            ->method('saveInstallFile')
            ->with($this->installFile)
            ->will($this->returnCallback(function (InstallFile $installFile) use ($packageDir) {
                \PHPUnit_Framework_Assert::assertFalse($installFile->hasPackageMetadata($packageDir));
            }));

        $this->assertTrue($this->installFile->hasPackageMetadata($packageDir));
        $this->assertTrue($this->manager->hasPackage('package1'));

        $this->manager->removePackage('package1');

        $this->assertFalse($this->installFile->hasPackageMetadata($packageDir));
        $this->assertFalse($this->manager->hasPackage('package1'));
    }

    public function testRemovePackageIgnoresUnknownName()
    {
        $this->initDefaultManager();

        $this->installFileStorage->expects($this->never())
            ->method('saveInstallFile');

        $this->manager->removePackage('foobar');
    }

    public function testRemovePackageIgnoresIfNoDescriptorFound()
    {
        $this->initDefaultManager();

        $this->installFileStorage->expects($this->never())
            ->method('saveInstallFile');

        $this->installFile->removePackageMetadata($this->packageDir1);

        $this->assertFalse($this->installFile->hasPackageMetadata($this->packageDir1));
        $this->assertTrue($this->manager->hasPackage('package1'));

        $this->manager->removePackage('package1');

        $this->assertFalse($this->installFile->hasPackageMetadata($this->packageDir1));
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

        $metadata1 = new PackageMetadata($this->packageDir1);
        $metadata1->setInstaller('Composer');
        $metadata2 = new PackageMetadata($this->packageDir2);
        $metadata2->setInstaller('User');
        $metadata3 = new PackageMetadata($this->packageDir3);
        $metadata3->setInstaller('Composer');

        $this->installFile->addPackageMetadata($metadata1);
        $this->installFile->addPackageMetadata($metadata2);
        $this->installFile->addPackageMetadata($metadata3);

        $this->manager = new PackageManager($this->environment, $this->packageFileStorage, $this->installFileStorage);

        $composerPackages = $this->manager->getPackagesByInstaller('Composer');
        $userPackages = $this->manager->getPackagesByInstaller('User');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Collection\PackageCollection', $composerPackages);
        $this->assertCount(2, $composerPackages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $composerPackages['package1']);
        $this->assertSame('package1', $composerPackages['package1']->getName());
        $this->assertSame($metadata1, $composerPackages['package1']->getMetadata());
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $composerPackages['package3']);
        $this->assertSame('package3', $composerPackages['package3']->getName());
        $this->assertSame($metadata3, $composerPackages['package3']->getMetadata());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Collection\PackageCollection', $userPackages);
        $this->assertCount(1, $userPackages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $userPackages['package2']);
        $this->assertSame('package2', $userPackages['package2']->getName());
        $this->assertSame($metadata2, $userPackages['package2']->getMetadata());
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

        $metadata1 = new PackageMetadata($this->packageDir1);
        $metadata1->setNew(false);
        $metadata2 = new PackageMetadata($this->packageDir2);
        $metadata2->setNew(false);

        $this->installFile->addPackageMetadata($metadata1);
        $this->installFile->addPackageMetadata($metadata2);

        $this->installFileStorage->expects($this->any())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($this->installFile));

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

        $this->manager = new PackageManager($this->environment, $this->packageFileStorage, $this->installFileStorage);
    }
}
