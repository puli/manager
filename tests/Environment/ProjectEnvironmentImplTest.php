<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Environment;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFile;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Config\ConfigFileStorage;
use Puli\RepositoryManager\Config\DefaultConfig;
use Puli\RepositoryManager\Environment\ProjectEnvironmentImpl;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironmentImplTest extends PHPUnit_Framework_TestCase
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
     * @var Config
     */
    private $config;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ConfigFileStorage
     */
    private $configFileStorage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/KeyValueStoreDiscoveryRecipeProviderTest'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->config = new DefaultConfig();
        $this->homeDir = $this->tempDir.'/home';
        $this->rootDir = $this->tempDir.'/root';
        $this->configFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Config\ConfigFileStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testCreate()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnCallback(function ($path, Config $baseConfig = null) {
                return new ConfigFile($path, $baseConfig);
            }));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnCallback(function ($path, Config $baseConfig = null) {
                return new RootPackageFile('vendor/root', $path, $baseConfig);
            }));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertSame($this->homeDir, $environment->getHomeDirectory());
        $this->assertSame($this->rootDir, $environment->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\EnvConfig', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\RootPackageFile', $environment->getRootPackageFile());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());

        // should be loaded from DefaultConfig
        $this->assertSame('.puli', $environment->getConfig()->get(Config::PULI_DIR));
    }

    public function testCreateWithoutHomeDir()
    {
        $this->configFileStorage->expects($this->never())
            ->method('loadConfigFile');

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnCallback(function ($path, Config $baseConfig = null) {
                return new RootPackageFile('vendor/root', $path, $baseConfig);
            }));

        $environment = new ProjectEnvironmentImpl(
            null,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertNull($environment->getHomeDirectory());
        $this->assertSame($this->rootDir, $environment->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\EnvConfig', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\RootPackageFile', $environment->getRootPackageFile());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());

        // should be loaded from DefaultConfig
        $this->assertSame('.puli', $environment->getConfig()->get(Config::PULI_DIR));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testFailIfNonExistingRootDir()
    {
        new ProjectEnvironmentImpl(
            $this->homeDir,
            __DIR__.'/foobar',
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\NoDirectoryException
     * @expectedExceptionMessage /puli.json
     */
    public function testFailIfRootDirNoDirectory()
    {
        new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir.'/puli.json',
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );
    }

    public function testCanonicalizeDirectories()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnCallback(function ($path, Config $baseConfig = null) {
                return new ConfigFile($path, $baseConfig);
            }));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnCallback(function ($path, Config $baseConfig = null) {
                return new RootPackageFile('vendor/root', $path, $baseConfig);
            }));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir.'/../home',
            $this->rootDir.'/../root',
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertSame($this->rootDir, $environment->getRootDirectory());
        $this->assertSame($this->homeDir, $environment->getHomeDirectory());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetRepository()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', $this->config)));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('vendor/root', $this->rootDir.'/puli.json', $this->config)));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertInstanceOf('Puli\Repository\FilesystemRepository', $environment->getRepository());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetDiscovery()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', $this->config)));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('vendor/root', $this->rootDir.'/puli.json', $this->config)));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertInstanceOf('Puli\Discovery\KeyValueStoreDiscovery', $environment->getDiscovery());
    }

    /**
     * @runInSeparateProcess
     */
    public function testFactoryIsRegeneratedAfterChangingGlobalConfig()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', $this->config)));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('vendor/root', $this->rootDir.'/puli.json', $this->config)));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        // Simulate previous generation of the factory file
        $this->config->set(Config::FACTORY_FILE, 'MyFactory.php');
        $this->config->set(Config::FACTORY_CLASS, 'Puli\RepositoryManager\Tests\Environment\Fixtures\MyFactory');
        file_put_contents($this->rootDir.'/MyFactory.php', file_get_contents(__DIR__.'/Fixtures/MyFactory.php'));
        clearstatcache(true, $this->rootDir.'/MyFactory.php');
        $lastModified = filemtime($this->rootDir.'/MyFactory.php');

        // Wait and modify global config
        sleep(1);
        file_put_contents($this->homeDir.'/config.json', 'updated config');

        $environment->getRepository();

        // File was updated
        clearstatcache(true, $this->rootDir.'/MyFactory.php');
        $this->assertGreaterThan($lastModified, filemtime($this->rootDir.'/MyFactory.php'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testGlobalConfigFileMayBeMissing()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', $this->config)));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('vendor/root', $this->rootDir.'/puli.json', $this->config)));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        // Simulate previous generation of the factory file
        $this->config->set(Config::FACTORY_FILE, 'MyFactory.php');
        $this->config->set(Config::FACTORY_CLASS, 'Puli\RepositoryManager\Tests\Environment\Fixtures\MyFactory');
        file_put_contents($this->rootDir.'/MyFactory.php', file_get_contents(__DIR__.'/Fixtures/MyFactory.php'));

        unlink($this->homeDir.'/config.json');

        // Shouldn't check filemtime() this time
        // MyFactory returns NULL
        $this->assertNull($environment->getRepository());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHomeDirMayBeMissing()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('vendor/root', $this->rootDir.'/puli.json', $this->config)));

        $environment = new ProjectEnvironmentImpl(
            null,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        // Simulate previous generation of the factory file
        $this->config->set(Config::FACTORY_FILE, 'MyFactory.php');
        $this->config->set(Config::FACTORY_CLASS, 'Puli\RepositoryManager\Tests\Environment\Fixtures\MyFactory');
        file_put_contents($this->rootDir.'/MyFactory.php', file_get_contents(__DIR__.'/Fixtures/MyFactory.php'));

        unlink($this->homeDir.'/config.json');

        // Shouldn't check filemtime() this time
        // MyFactory returns NULL
        $this->assertNull($environment->getRepository());
    }

    /**
     * @runInSeparateProcess
     */
    public function testFactoryIsRegeneratedAfterChangingRootPackageFile()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', $this->config)));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('vendor/root', $this->rootDir.'/puli.json', $this->config)));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        // Simulate previous generation of the factory file
        $this->config->set(Config::FACTORY_FILE, 'MyFactory.php');
        $this->config->set(Config::FACTORY_CLASS, 'Puli\RepositoryManager\Tests\Environment\Fixtures\MyFactory');
        file_put_contents($this->rootDir.'/MyFactory.php', file_get_contents(__DIR__.'/Fixtures/MyFactory.php'));
        clearstatcache(true, $this->rootDir.'/MyFactory.php');
        $lastModified = filemtime($this->rootDir.'/MyFactory.php');

        // Wait and modify package file
        sleep(1);
        file_put_contents($this->rootDir.'/puli.json', 'updated config');

        $environment->getRepository();

        // File was updated
        clearstatcache(true, $this->rootDir.'/MyFactory.php');
        $this->assertGreaterThan($lastModified, filemtime($this->rootDir.'/MyFactory.php'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testFactoryIsNotRegeneratedIfAutoGenerateDisabled()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', $this->config)));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('vendor/root', $this->rootDir.'/puli.json', $this->config)));

        $environment = new ProjectEnvironmentImpl(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        // Simulate previous generation of the factory file
        $this->config->set(Config::FACTORY_AUTO_GENERATE, false);
        $this->config->set(Config::FACTORY_FILE, 'MyFactory.php');
        $this->config->set(Config::FACTORY_CLASS, 'Puli\RepositoryManager\Tests\Environment\Fixtures\MyFactory');
        file_put_contents($this->rootDir.'/MyFactory.php', file_get_contents(__DIR__.'/Fixtures/MyFactory.php'));

        // Wait and modify global config
        sleep(1);
        file_put_contents($this->homeDir.'/config.json', 'updated config');

        $environment->getRepository();

        // File was not updated
        $this->assertSame(file_get_contents(__DIR__.'/Fixtures/MyFactory.php'), file_get_contents($this->rootDir.'/MyFactory.php'));
    }
}
