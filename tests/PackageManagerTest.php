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
use Puli\PackageManager\PackageManager;
use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
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
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigReaderInterface
     */
    private $packageConfigReader;

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
        $this->packageConfigReader = $this->getMock('Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface');

        $this->rootDir = $this->tempDir.'/root-package';
        $this->package1Dir = $this->tempDir.'/package1';
        $this->package2Dir = $this->tempDir.'/package2';

        $this->rootConfig = new RootPackageConfig($this->rootDir);
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

        $manager = new PackageManager($this->rootDir, $this->dispatcher, $this->repositoryConfigReader, $this->packageConfigReader);

        $this->assertSame($rootConfig, $manager->getRootPackageConfig());
        $this->assertSame($packageRepoConfig, $manager->getRepositoryConfig());

        $packages = $manager->getPackageRepository()->getPackages();

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

    private function initDefaultManager()
    {
        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->rootDir = $this->tempDir.'/root-package';
        $this->package1Dir = $this->tempDir.'/package1';
        $this->package2Dir = $this->tempDir.'/package2';
        $this->rootConfig->setPackageRepositoryConfig('repository.json');
        $this->packageRepoConfig->addPackageDescriptor(new PackageDescriptor('../package1'));
        $this->packageRepoConfig->addPackageDescriptor(new PackageDescriptor('../package2'));

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

        $this->manager = new PackageManager($this->rootDir, $this->dispatcher, $this->repositoryConfigReader, $this->packageConfigReader);
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
}
