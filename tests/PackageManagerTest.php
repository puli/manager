<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests;

use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface;
use Puli\PackageManager\PackageManager;
use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface;
use Puli\PackageManager\Tests\Fixtures\TestPlugin;
use Puli\Repository\ResourceRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigWriterInterface
     */
    private $repositoryConfigWriter;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigReaderInterface
     */
    private $packageConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigWriterInterface
     */
    private $packageConfigWriter;

    /**
     * @var PackageManager
     */
    private $manager;

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
     * @var PackageRepositoryConfig
     */
    private $packageRepoConfig;

    protected function setUp()
    {
        while (false === mkdir($this->tempDir = sys_get_temp_dir().'/puli-plugin/PackageManagerTest'.rand(10000, 99999), 0777, true)) {}

        $this->dispatcher = new EventDispatcher();
        $this->repositoryConfigReader = $this->getMock('Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface');
        $this->repositoryConfigWriter = $this->getMock('Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface');
        $this->packageConfigReader = $this->getMock('Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface');
        $this->packageConfigWriter = $this->getMock('Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface');

        $this->rootDir = $this->tempDir.'/root-package';
        $this->package1Dir = $this->tempDir.'/package1';
        $this->package2Dir = $this->tempDir.'/package2';
        $this->package3Dir = $this->tempDir.'/package3';

        $this->rootConfig = new RootPackageConfig('root');
        $this->package1Config = new PackageConfig('package1');
        $this->package2Config = new PackageConfig('package2');
        $this->packageRepoConfig = new PackageRepositoryConfig();
    }

    protected function tearDown()
    {
        // Make sure initDefaultManager() is called again
        $this->manager = null;

        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testLoadPackageRepository()
    {
        $rootConfig = new RootPackageConfig('root');
        $rootConfig->setPackageRepositoryConfig('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package2');

        $packageRepoConfig = new PackageRepositoryConfig();
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor('relative/path/to/package1'));
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor('/absolute/path/to/package2'));

        $this->packageConfigReader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue($rootConfig));

        $this->packageConfigReader->expects($this->at(1))
            ->method('readPackageConfig')
            ->with($this->rootDir.'/relative/path/to/package1/puli.json')
            ->will($this->returnValue($package1Config));
        $this->packageConfigReader->expects($this->at(2))
            ->method('readPackageConfig')
            ->with('/absolute/path/to/package2/puli.json')
            ->will($this->returnValue($package2Config));

        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($packageRepoConfig));

        $manager = new PackageManager(
            $this->rootDir,
            $this->dispatcher,
            $this->repositoryConfigReader,
            $this->repositoryConfigWriter,
            $this->packageConfigReader,
            $this->packageConfigWriter
        );

        $this->assertSame($rootConfig, $manager->getRootPackageConfig());
        $this->assertSame($packageRepoConfig, $manager->getRepositoryConfig());

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertInstanceOf('Puli\PackageManager\Package\RootPackage', $packages['root']);
        $this->assertSame('root', $packages['root']->getName());
        $this->assertSame($this->rootDir, $packages['root']->getInstallPath());
        $this->assertSame($rootConfig, $packages['root']->getConfig());

        $this->assertInstanceOf('Puli\PackageManager\Package\Package', $packages['package1']);
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame($this->rootDir.'/relative/path/to/package1', $packages['package1']->getInstallPath());
        $this->assertSame($package1Config, $packages['package1']->getConfig());

        $this->assertInstanceOf('Puli\PackageManager\Package\Package', $packages['package2']);
        $this->assertSame('package2', $packages['package2']->getName());
        $this->assertSame('/absolute/path/to/package2', $packages['package2']->getInstallPath());
        $this->assertSame($package2Config, $packages['package2']->getConfig());
    }

    /**
     * @expectedException \Puli\PackageManager\NameConflictException
     */
    public function testLoadPackageRepositoryFailsIfNameConflict()
    {
        $rootConfig = new RootPackageConfig('root');
        $rootConfig->setPackageRepositoryConfig('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package1');

        $packageRepoConfig = new PackageRepositoryConfig();
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor($this->package1Dir));
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor($this->package2Dir));

        $this->packageConfigReader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue($rootConfig));

        $this->packageConfigReader->expects($this->at(1))
            ->method('readPackageConfig')
            ->with($this->package1Dir.'/puli.json')
            ->will($this->returnValue($package1Config));
        $this->packageConfigReader->expects($this->at(2))
            ->method('readPackageConfig')
            ->with($this->package2Dir.'/puli.json')
            ->will($this->returnValue($package2Config));

        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($packageRepoConfig));

        new PackageManager(
            $this->rootDir,
            $this->dispatcher,
            $this->repositoryConfigReader,
            $this->repositoryConfigWriter,
            $this->packageConfigReader,
            $this->packageConfigWriter
        );
    }

    private function initDefaultManager()
    {
        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->rootDir = $this->tempDir.'/root-package';
        $this->package1Dir = $this->tempDir.'/package1';
        $this->package2Dir = $this->tempDir.'/package2';
        $this->rootConfig->setPackageRepositoryConfig('repository.json');
        $this->packageRepoConfig->addPackageDescriptor(new PackageDescriptor('../package1', false));
        $this->packageRepoConfig->addPackageDescriptor(new PackageDescriptor('../package2', false));

        $this->packageConfigReader->expects($this->once())
            ->method('readRootPackageConfig')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue($this->rootConfig));

        $this->packageConfigReader->expects($this->at(1))
            ->method('readPackageConfig')
            ->with($this->package1Dir.'/puli.json')
            ->will($this->returnValue($this->package1Config));
        $this->packageConfigReader->expects($this->at(2))
            ->method('readPackageConfig')
            ->with($this->package2Dir.'/puli.json')
            ->will($this->returnValue($this->package2Config));

        $this->repositoryConfigReader->expects($this->once())
            ->method('readRepositoryConfig')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($this->packageRepoConfig));

        $this->manager = new PackageManager(
            $this->rootDir,
            $this->dispatcher,
            $this->repositoryConfigReader,
            $this->repositoryConfigWriter,
            $this->packageConfigReader,
            $this->packageConfigWriter
        );
    }

    public function testGenerateResourceRepository()
    {
        $this->initDefaultManager();

        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));
        $this->package1Config->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $this->package2Config->addResourceDescriptor(new ResourceDescriptor('/package2', 'resources'));

        $this->manager->generateResourceRepository();

        $this->assertFileExists($this->tempDir.'/cache');
        $this->assertFileExists($this->tempDir.'/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
        $this->assertSame($this->package1Dir.'/resources', $repo->get('/package1')->getLocalPath());
        $this->assertSame($this->package2Dir.'/resources', $repo->get('/package2')->getLocalPath());
    }

    public function testGenerateResourceRepositoryReplacesExistingFiles()
    {
        $this->initDefaultManager();

        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        mkdir($this->tempDir.'/cache');
        touch($this->tempDir.'/cache/old');
        touch($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->generateResourceRepository();

        $this->assertFileExists($this->tempDir.'/cache');
        $this->assertFileExists($this->tempDir.'/repository.php');
        $this->assertFileNotExists($this->tempDir.'/cache/old');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testGenerateResourceRepositoryWithRelativePaths()
    {
        $this->initDefaultManager();

        $this->rootConfig->setResourceRepositoryCache('cache-dir/cache');
        $this->rootConfig->setGeneratedResourceRepository('repo-dir/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->generateResourceRepository();

        $this->assertFileExists($this->rootDir.'/cache-dir/cache');
        $this->assertFileExists($this->rootDir.'/repo-dir/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->rootDir.'/repo-dir/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testGenerateResourceRepositoryWithCustomRepositoryPath()
    {
        $this->initDefaultManager();

        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->generateResourceRepository($this->tempDir.'/custom-repository.php');

        $this->assertFileExists($this->tempDir.'/cache');
        $this->assertFileExists($this->tempDir.'/custom-repository.php');
        $this->assertFileNotExists($this->tempDir.'/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/custom-repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testGenerateResourceRepositoryWithCustomCachePath()
    {
        $this->initDefaultManager();

        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->generateResourceRepository(null, $this->tempDir.'/custom-cache');

        $this->assertFileExists($this->tempDir.'/custom-cache');
        $this->assertFileNotExists($this->tempDir.'/cache');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testPlugins()
    {
        $this->rootConfig->addPluginClass(__NAMESPACE__.'\Fixtures\TestPlugin');

        $this->initDefaultManager();

        $this->assertSame($this->manager, TestPlugin::getManager());
        $this->assertSame($this->dispatcher, TestPlugin::getDispatcher());
    }

    public function testInstallPackage()
    {
        $this->initDefaultManager();

        $config = new PackageConfig('package3');

        $this->packageConfigReader->expects($this->at(0))
            ->method('readPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->repositoryConfigWriter->expects($this->once())
            ->method('writeRepositoryConfig')
            ->with($this->isInstanceOf('Puli\PackageManager\Repository\Config\PackageRepositoryConfig'))
            ->will($this->returnCallback(function (PackageRepositoryConfig $config) {
                $descriptors = $config->getPackageDescriptors();

                \PHPUnit_Framework_Assert::assertCount(3, $descriptors);
                \PHPUnit_Framework_Assert::assertSame('../package1', $descriptors[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($descriptors[0]->isNew());
                \PHPUnit_Framework_Assert::assertSame('../package2', $descriptors[1]->getInstallPath());
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

        $this->packageConfigReader->expects($this->at(0))
            ->method('readPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->repositoryConfigWriter->expects($this->once())
            ->method('writeRepositoryConfig')
            ->with($this->isInstanceOf('Puli\PackageManager\Repository\Config\PackageRepositoryConfig'))
            ->will($this->returnCallback(function (PackageRepositoryConfig $config) {
                $descriptors = $config->getPackageDescriptors();

                \PHPUnit_Framework_Assert::assertCount(3, $descriptors);
                \PHPUnit_Framework_Assert::assertSame('../package1', $descriptors[0]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($descriptors[0]->isNew());
                \PHPUnit_Framework_Assert::assertSame('../package2', $descriptors[1]->getInstallPath());
                \PHPUnit_Framework_Assert::assertFalse($descriptors[1]->isNew());
                \PHPUnit_Framework_Assert::assertSame('../package3', $descriptors[2]->getInstallPath());
                \PHPUnit_Framework_Assert::assertTrue($descriptors[2]->isNew());
            }));

        $this->manager->installPackage('../package3');
    }

    public function testInstallPackageDoesNothingIfAlreadyInstalled()
    {
        $this->initDefaultManager();

        $this->packageConfigReader->expects($this->never())
            ->method('readPackageConfig');

        $this->repositoryConfigWriter->expects($this->never())
            ->method('writeRepositoryConfig');

        $this->manager->installPackage($this->package2Dir);
    }

    /**
     * @expectedException \Puli\PackageManager\NameConflictException
     */
    public function testInstallPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $config = new PackageConfig('package2');

        $this->packageConfigReader->expects($this->at(0))
            ->method('readPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->repositoryConfigWriter->expects($this->never())
            ->method('writeRepositoryConfig');

        $this->manager->installPackage($this->package3Dir);
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

        $this->assertInstanceOf('Puli\PackageManager\Package\RootPackage', $rootPackage);
        $this->assertSame('root', $rootPackage->getName());
        $this->assertSame($this->rootDir, $rootPackage->getInstallPath());
        $this->assertSame($this->rootConfig, $rootPackage->getConfig());

        $package1 = $this->manager->getPackage('package1');

        $this->assertInstanceOf('Puli\PackageManager\Package\Package', $package1);
        $this->assertSame('package1', $package1->getName());
        $this->assertSame($this->package1Dir, $package1->getInstallPath());
        $this->assertSame($this->package1Config, $package1->getConfig());
    }

    /**
     * @expectedException \Puli\PackageManager\Repository\NoSuchPackageException
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

        $this->assertInstanceOf('Puli\PackageManager\Package\RootPackage', $rootPackage);
        $this->assertSame('root', $rootPackage->getName());
        $this->assertSame($this->rootDir, $rootPackage->getInstallPath());
        $this->assertSame($this->rootConfig, $rootPackage->getConfig());
    }
}
