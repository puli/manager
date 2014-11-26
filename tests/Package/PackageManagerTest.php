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

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Package\Config\PackageConfig;
use Puli\RepositoryManager\Package\Config\PackageConfigStorage;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Package\InstallFile\InstallFile;
use Puli\RepositoryManager\Package\InstallFile\InstallFileStorage;
use Puli\RepositoryManager\Package\InstallFile\PackageDescriptor;
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
    private $package1Dir;

    /**
     * @var string
     */
    private $package2Dir;

    /**
     * @var string
     */
    private $package3Dir;

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var RootPackageConfig
     */
    private $rootConfig;

    /**
     * @var PackageConfig
     */
    private $package1Config;

    /**
     * @var PackageConfig
     */
    private $package2Config;

    /**
     * @var InstallFile
     */
    private $installFile;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var \Puli\RepositoryManager\Tests\Package\Fixtures\TestProjectEnvironment
     */
    private $environment;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigStorage
     */
    private $packageConfigStorage;

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
        $this->package1Dir = __DIR__.'/Fixtures/package1';
        $this->package2Dir = __DIR__.'/Fixtures/package2';
        $this->package3Dir = __DIR__.'/Fixtures/package3';

        $this->globalConfig = new GlobalConfig();
        $this->rootConfig = new RootPackageConfig($this->globalConfig, 'root');
        $this->package1Config = new PackageConfig('package1');
        $this->package2Config = new PackageConfig('package2');
        $this->installFile = new InstallFile();

        $this->packageConfigStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\Config\PackageConfigStorage')
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
        $this->rootConfig->setInstallFile('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package2');

        $installFile = new InstallFile();
        $installFile->addPackageDescriptor(new PackageDescriptor('../package1'));
        $installFile->addPackageDescriptor(new PackageDescriptor(realpath($this->rootDir.'/../package2')));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        $this->packageConfigStorage->expects($this->at(0))
            ->method('loadPackageConfig')
            ->with($this->package1Dir.'/puli.json')
            ->will($this->returnValue($package1Config));
        $this->packageConfigStorage->expects($this->at(1))
            ->method('loadPackageConfig')
            ->with($this->package2Dir.'/puli.json')
            ->will($this->returnValue($package2Config));

        $manager = new PackageManager($this->environment, $this->packageConfigStorage, $this->installFileStorage);

        $this->assertSame($installFile, $manager->getInstallFile());

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertInstanceOf('Puli\RepositoryManager\Package\RootPackage', $packages['root']);
        $this->assertSame('root', $packages['root']->getName());
        $this->assertSame($this->rootDir, $packages['root']->getInstallPath());
        $this->assertSame($this->rootConfig, $packages['root']->getConfig());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $packages['package1']);
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame($this->package1Dir, $packages['package1']->getInstallPath());
        $this->assertSame($package1Config, $packages['package1']->getConfig());

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $packages['package2']);
        $this->assertSame('package2', $packages['package2']->getName());
        $this->assertSame($this->package2Dir, $packages['package2']->getInstallPath());
        $this->assertSame($package2Config, $packages['package2']->getConfig());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NameConflictException
     */
    public function testLoadPackagesFailsIfNameConflict()
    {
        $this->rootConfig->setInstallFile('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package1');

        $installFile = new InstallFile();
        $installFile->addPackageDescriptor(new PackageDescriptor($this->package1Dir));
        $installFile->addPackageDescriptor(new PackageDescriptor($this->package2Dir));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        $this->packageConfigStorage->expects($this->at(0))
            ->method('loadPackageConfig')
            ->with($this->package1Dir.'/puli.json')
            ->will($this->returnValue($package1Config));
        $this->packageConfigStorage->expects($this->at(1))
            ->method('loadPackageConfig')
            ->with($this->package2Dir.'/puli.json')
            ->will($this->returnValue($package2Config));

        new PackageManager($this->environment, $this->packageConfigStorage, $this->installFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage foobar
     */
    public function testLoadPackagesFailsIfPackageDirNotFound()
    {
        $this->rootConfig->setInstallFile('repository.json');

        $installFile = new InstallFile();
        $installFile->addPackageDescriptor(new PackageDescriptor('foobar'));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        new PackageManager($this->environment, $this->packageConfigStorage, $this->installFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testLoadPackagesFailsIfPackageNoDirectory()
    {
        $this->rootConfig->setInstallFile('repository.json');

        $installFile = new InstallFile();
        $installFile->addPackageDescriptor(new PackageDescriptor(__DIR__.'/Fixtures/file'));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($installFile));

        new PackageManager($this->environment, $this->packageConfigStorage, $this->installFileStorage);
    }

    public function testInstallPackage()
    {
        $this->initDefaultManager();

        $config = new PackageConfig('package3');

        $this->packageConfigStorage->expects($this->once())
            ->method('loadPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->installFileStorage->expects($this->once())
            ->method('saveInstallFile')
            ->with($this->isInstanceOf('Puli\RepositoryManager\Package\InstallFile\InstallFile'))
            ->will($this->returnCallback(function (InstallFile $installFile) {
                $descriptors = $installFile->getPackageDescriptors();

                \PHPUnit_Framework_Assert::assertCount(3, $descriptors);
                \PHPUnit_Framework_Assert::assertSame($this->package1Dir, $descriptors[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($descriptors[0]->isNew());
                \PHPUnit_Framework_Assert::assertSame($this->package2Dir, $descriptors[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($descriptors[1]->isNew());
                \PHPUnit_Framework_Assert::assertSame('../package3', $descriptors[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertTrue($descriptors[2]->isNew());
            }));

        $this->manager->installPackage($this->package3Dir);
    }

    public function testInstallPackageWithRelativePath()
    {
        $this->initDefaultManager();

        $config = new PackageConfig('package3');

        $this->packageConfigStorage->expects($this->once())
            ->method('loadPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->installFileStorage->expects($this->once())
            ->method('saveInstallFile')
            ->with($this->isInstanceOf('Puli\RepositoryManager\Package\InstallFile\InstallFile'))
            ->will($this->returnCallback(function (InstallFile $installFile) {
                $descriptors = $installFile->getPackageDescriptors();

                \PHPUnit_Framework_Assert::assertCount(3, $descriptors);
                \PHPUnit_Framework_Assert::assertSame($this->package1Dir, $descriptors[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($descriptors[0]->isNew());
                \PHPUnit_Framework_Assert::assertSame($this->package2Dir, $descriptors[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($descriptors[1]->isNew());
                \PHPUnit_Framework_Assert::assertSame('../package3', $descriptors[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertTrue($descriptors[2]->isNew());
            }));

        $this->manager->installPackage('../package3');
    }

    public function testInstallPackageDoesNothingIfAlreadyInstalled()
    {
        $this->initDefaultManager();

        $this->packageConfigStorage->expects($this->never())
            ->method('loadPackageConfig');

        $this->installFileStorage->expects($this->never())
            ->method('saveInstallFile');

        $this->manager->installPackage($this->package2Dir);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NameConflictException
     */
    public function testInstallPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $config = new PackageConfig('package2');

        $this->packageConfigStorage->expects($this->at(0))
            ->method('loadPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->installFileStorage->expects($this->never())
            ->method('saveInstallFile');

        $this->manager->installPackage($this->package3Dir);
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

        $this->assertTrue($this->manager->isPackageInstalled($this->package1Dir));
        $this->assertFalse($this->manager->isPackageInstalled($this->package3Dir));
    }

    public function testIsPackageInstalledAcceptsRelativePath()
    {
        $this->initDefaultManager();

        $this->assertTrue($this->manager->isPackageInstalled('../package1'));
        $this->assertFalse($this->manager->isPackageInstalled('../package3'));
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
        $this->assertSame($this->rootConfig, $rootPackage->getConfig());

        $package1 = $this->manager->getPackage('package1');

        $this->assertInstanceOf('Puli\RepositoryManager\Package\Package', $package1);
        $this->assertSame('package1', $package1->getName());
        $this->assertSame($this->package1Dir, $package1->getInstallPath());
        $this->assertSame($this->package1Config, $package1->getConfig());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\Collection\NoSuchPackageException
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
        $this->assertSame($this->rootConfig, $rootPackage->getConfig());
    }

    private function initEnvironment()
    {
        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->globalConfig,
            $this->rootConfig,
            $this->dispatcher
        );
    }

    private function initDefaultManager()
    {
        $this->rootConfig->setInstallFile('repository.json');
        $this->installFile->addPackageDescriptor(new PackageDescriptor($this->package1Dir, false));
        $this->installFile->addPackageDescriptor(new PackageDescriptor($this->package2Dir, false));

        $this->installFileStorage->expects($this->once())
            ->method('loadInstallFile')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($this->installFile));

        $this->packageConfigStorage->expects($this->at(0))
            ->method('loadPackageConfig')
            ->with($this->package1Dir.'/puli.json')
            ->will($this->returnValue($this->package1Config));
        $this->packageConfigStorage->expects($this->at(1))
            ->method('loadPackageConfig')
            ->with($this->package2Dir.'/puli.json')
            ->will($this->returnValue($this->package2Config));

        $this->manager = new PackageManager($this->environment, $this->packageConfigStorage, $this->installFileStorage);
    }
}
