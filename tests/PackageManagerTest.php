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

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\ConfigManager;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\Reader\PackageConfigReaderInterface;
use Puli\PackageManager\Package\Config\ResourceDescriptor;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Package\Config\Writer\PackageConfigWriterInterface;
use Puli\PackageManager\PackageManager;
use Puli\PackageManager\PuliEnvironment;
use Puli\PackageManager\Repository\Config\PackageDescriptor;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface;
use Puli\PackageManager\Tests\Config\Fixtures\TestPlugin;
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
    const PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\OtherPlugin';

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
     * @var \PHPUnit_Framework_MockObject_MockObject|PuliEnvironment
     */
    private $environment;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ConfigManager
     */
    private $configManager;

    /**
     * @var PackageManager
     */
    private $manager;

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
     * @var PackageRepositoryConfig
     */
    private $packageRepoConfig;

    protected function setUp()
    {
        while (false === mkdir($this->tempDir = sys_get_temp_dir().'/puli-manager/PackageManagerTest_temp'.rand(10000, 99999), 0777, true)) {}

        $this->dispatcher = new EventDispatcher();

        $this->rootDir = __DIR__.'/Fixtures/root-package';
        $this->package1Dir = __DIR__.'/Fixtures/package1';
        $this->package2Dir = __DIR__.'/Fixtures/package2';
        $this->package3Dir = __DIR__.'/Fixtures/package3';

        $this->globalConfig = new GlobalConfig();
        $this->rootConfig = new RootPackageConfig($this->globalConfig, 'root');
        $this->package1Config = new PackageConfig('package1');
        $this->package2Config = new PackageConfig('package2');
        $this->packageRepoConfig = new PackageRepositoryConfig();

        $this->environment = $this->getMockBuilder('Puli\PackageManager\PuliEnvironment')
            ->disableOriginalConstructor()
            ->getMock();
        $this->configManager = $this->getMockBuilder('Puli\PackageManager\ConfigManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->environment->expects($this->any())
            ->method('getGlobalConfig')
            ->will($this->returnValue($this->globalConfig));
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
        $rootConfig = new RootPackageConfig($this->globalConfig, 'root');
        $rootConfig->setPackageRepositoryConfig('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package2');

        $packageRepoConfig = new PackageRepositoryConfig();
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor('relative/path/to/package1'));
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor('/absolute/path/to/package2'));

        $this->configManager->expects($this->at(0))
            ->method('loadRootPackageConfig')
            ->with($this->rootDir.'/puli.json', $this->globalConfig)
            ->will($this->returnValue($rootConfig));

        $this->configManager->expects($this->at(1))
            ->method('loadRepositoryConfig')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($packageRepoConfig));

        $this->configManager->expects($this->at(2))
            ->method('loadPackageConfig')
            ->with($this->rootDir.'/relative/path/to/package1/puli.json')
            ->will($this->returnValue($package1Config));
        $this->configManager->expects($this->at(3))
            ->method('loadPackageConfig')
            ->with('/absolute/path/to/package2/puli.json')
            ->will($this->returnValue($package2Config));

        $manager = new PackageManager($this->rootDir, $this->environment, $this->configManager, $this->dispatcher);

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
        $rootConfig = new RootPackageConfig($this->globalConfig, 'root');
        $rootConfig->setPackageRepositoryConfig('repository.json');
        $package1Config = new PackageConfig('package1');
        $package2Config = new PackageConfig('package1');

        $packageRepoConfig = new PackageRepositoryConfig();
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor($this->package1Dir));
        $packageRepoConfig->addPackageDescriptor(new PackageDescriptor($this->package2Dir));

        $this->configManager->expects($this->at(0))
            ->method('loadRootPackageConfig')
            ->with($this->rootDir.'/puli.json', $this->globalConfig)
            ->will($this->returnValue($rootConfig));

        $this->configManager->expects($this->at(1))
            ->method('loadRepositoryConfig')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($packageRepoConfig));

        $this->configManager->expects($this->at(2))
            ->method('loadPackageConfig')
            ->with($this->package1Dir.'/puli.json')
            ->will($this->returnValue($package1Config));
        $this->configManager->expects($this->at(3))
            ->method('loadPackageConfig')
            ->with($this->package2Dir.'/puli.json')
            ->will($this->returnValue($package2Config));

        new PackageManager($this->rootDir, $this->environment, $this->configManager, $this->dispatcher);
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testFailIfNonExistingRootDir()
    {
        new PackageManager(__DIR__.'/foobar', $this->environment, $this->configManager, $this->dispatcher);
    }

    /**
     * @expectedException \Puli\PackageManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testFailIfRootDirNoDirectory()
    {
        new PackageManager($this->rootDir.'/file', $this->environment, $this->configManager, $this->dispatcher);
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
        $filesystem = new Filesystem();
        $filesystem->mirror($this->rootDir, $this->tempDir);

        $this->rootDir = $this->tempDir;

        $this->initDefaultManager();

        $this->rootConfig->setResourceRepositoryCache('cache-dir/cache');
        $this->rootConfig->setGeneratedResourceRepository('repo-dir/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->generateResourceRepository();

        $this->assertFileExists($this->tempDir.'/cache-dir/cache');
        $this->assertFileExists($this->tempDir.'/repo-dir/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repo-dir/repository.php';

        $this->assertSame($this->tempDir.'/resources', $repo->get('/root')->getLocalPath());
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
        $this->rootConfig->addPluginClass(__NAMESPACE__.'\Config\Fixtures\TestPlugin');

        $this->initDefaultManager();

        $this->assertSame($this->manager, TestPlugin::getManager());
        $this->assertSame($this->dispatcher, TestPlugin::getDispatcher());
    }

    public function testInstallPackage()
    {
        $this->initDefaultManager();

        $config = new PackageConfig('package3');

        $this->configManager->expects($this->at(0))
            ->method('loadPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->configManager->expects($this->once())
            ->method('saveRepositoryConfig')
            ->with($this->isInstanceOf('Puli\PackageManager\Repository\Config\PackageRepositoryConfig'))
            ->will($this->returnCallback(function (PackageRepositoryConfig $config) {
                $descriptors = $config->getPackageDescriptors();

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

        $this->configManager->expects($this->at(0))
            ->method('loadPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->configManager->expects($this->once())
            ->method('saveRepositoryConfig')
            ->with($this->isInstanceOf('Puli\PackageManager\Repository\Config\PackageRepositoryConfig'))
            ->will($this->returnCallback(function (PackageRepositoryConfig $config) {
                $descriptors = $config->getPackageDescriptors();

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

        $this->configManager->expects($this->never())
            ->method('loadPackageConfig');

        $this->configManager->expects($this->never())
            ->method('saveRepositoryConfig');

        $this->manager->installPackage($this->package2Dir);
    }

    /**
     * @expectedException \Puli\PackageManager\NameConflictException
     */
    public function testInstallPackageFailsIfNameConflict()
    {
        $this->initDefaultManager();

        $config = new PackageConfig('package2');

        $this->configManager->expects($this->at(0))
            ->method('loadPackageConfig')
            ->with($this->package3Dir.'/puli.json')
            ->will($this->returnValue($config));

        $this->configManager->expects($this->never())
            ->method('saveRepositoryConfig');

        $this->manager->installPackage($this->package3Dir);
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testInstallPackageFailsIfDirectoryNotFound()
    {
        $this->initDefaultManager();

        $this->manager->installPackage(__DIR__.'/foobar');
    }

    /**
     * @expectedException \Puli\PackageManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testInstallPackageFailsIfNoDirectory()
    {
        $this->initDefaultManager();

        $this->manager->installPackage($this->rootDir.'/file');
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

    public function testInstallLocalPlugin()
    {
        $this->initDefaultManager();

        $this->environment->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->environment->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->configManager->expects($this->once())
            ->method('savePackageConfig')
            ->with($this->isInstanceOf('Puli\PackageManager\Package\Config\RootPackageConfig'))
            ->will($this->returnCallback(function (RootPackageConfig $config) {
                \PHPUnit_Framework_Assert::assertSame(array(self::PLUGIN_CLASS), $config->getPluginClasses(false));
                \PHPUnit_Framework_Assert::assertSame(array(self::PLUGIN_CLASS), $config->getPluginClasses(true));
            }));

        $this->manager->installPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses(false));
    }

    public function testInstallLocalDoesNothingIfPluginExistsGlobally()
    {
        $this->initDefaultManager();

        $this->environment->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(true));

        $this->environment->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->configManager->expects($this->never())
            ->method('savePackageConfig');

        $this->manager->installPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(), $this->manager->getPluginClasses(false));
    }

    public function testInstallLocalDoesNothingIfPluginExistsLocally()
    {
        $this->initDefaultManager();

        $this->environment->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->environment->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->configManager->expects($this->never())
            ->method('savePackageConfig');

        $this->rootConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->installPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses(false));
    }

    public function testInstallGlobalPlugin()
    {
        $this->initDefaultManager();

        $this->environment->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->environment->expects($this->once())
            ->method('installGlobalPluginClass')
            ->with(self::PLUGIN_CLASS);

        $this->configManager->expects($this->never())
            ->method('savePackageConfig');

        $this->manager->installPluginClass(self::PLUGIN_CLASS, true);

        $this->assertSame(array(), $this->manager->getPluginClasses(false));
    }

    public function testInstallGlobalDoesNothingIfPluginExistsGlobally()
    {
        $this->initDefaultManager();

        $this->environment->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(true));

        $this->environment->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->configManager->expects($this->never())
            ->method('savePackageConfig');

        $this->manager->installPluginClass(self::PLUGIN_CLASS, true);

        $this->assertSame(array(), $this->manager->getPluginClasses(false));
    }

    public function testInstallGlobalWritesConfigEvenThoughPluginExistsLocally()
    {
        $this->initDefaultManager();

        $this->environment->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->environment->expects($this->once())
            ->method('installGlobalPluginClass')
            ->with(self::PLUGIN_CLASS);

        $this->configManager->expects($this->never())
            ->method('savePackageConfig');

        $this->rootConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->installPluginClass(self::PLUGIN_CLASS, true);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses(false));
    }

    public function testIsPluginClassInstalled()
    {
        $this->initDefaultManager();

        $this->globalConfig->addPluginClass(self::PLUGIN_CLASS);
        $this->rootConfig->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertTrue($this->manager->isPluginClassInstalled(self::PLUGIN_CLASS, true));
        $this->assertTrue($this->manager->isPluginClassInstalled(self::OTHER_PLUGIN_CLASS, true));
        $this->assertFalse($this->manager->isPluginClassInstalled(self::PLUGIN_CLASS, false));
        $this->assertTrue($this->manager->isPluginClassInstalled(self::OTHER_PLUGIN_CLASS, false));

        $this->assertFalse($this->manager->isPluginClassInstalled('foobar', true));
        $this->assertFalse($this->manager->isPluginClassInstalled('foobar', false));
    }

    private function initDefaultManager()
    {
        $this->rootConfig->setPackageRepositoryConfig('repository.json');
        $this->packageRepoConfig->addPackageDescriptor(new PackageDescriptor($this->package1Dir, false));
        $this->packageRepoConfig->addPackageDescriptor(new PackageDescriptor($this->package2Dir, false));

        $this->configManager->expects($this->at(0))
            ->method('loadRootPackageConfig')
            ->with($this->rootDir.'/puli.json', $this->globalConfig)
            ->will($this->returnValue($this->rootConfig));

        $this->configManager->expects($this->at(1))
            ->method('loadRepositoryConfig')
            ->with($this->rootDir.'/repository.json')
            ->will($this->returnValue($this->packageRepoConfig));

        $this->configManager->expects($this->at(2))
            ->method('loadPackageConfig')
            ->with($this->package1Dir.'/puli.json')
            ->will($this->returnValue($this->package1Config));
        $this->configManager->expects($this->at(3))
            ->method('loadPackageConfig')
            ->with($this->package2Dir.'/puli.json')
            ->will($this->returnValue($this->package2Config));

        $this->manager = new PackageManager($this->rootDir, $this->environment, $this->configManager, $this->dispatcher);
    }
}
