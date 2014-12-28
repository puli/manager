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
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Config\DefaultConfig;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironmentTest extends PHPUnit_Framework_TestCase
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
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/KeyValueStoreDiscoveryGeneratorTest'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->homeDir = $this->tempDir.'/home';
        $this->rootDir = $this->tempDir.'/root';
        $this->configFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
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
                return new RootPackageFile('root', $path, $baseConfig);
            }));

        $environment = new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertSame($this->homeDir, $environment->getHomeDirectory());
        $this->assertSame($this->rootDir, $environment->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\EnvConfig', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageFile\RootPackageFile', $environment->getRootPackageFile());
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
                return new RootPackageFile('root', $path, $baseConfig);
            }));

        $environment = new ProjectEnvironment(
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
        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageFile\RootPackageFile', $environment->getRootPackageFile());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());

        // should be loaded from DefaultConfig
        $this->assertSame('.puli', $environment->getConfig()->get(Config::PULI_DIR));
    }

    /**
     * @expectedException \Puli\RepositoryManager\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testFailIfNonExistingRootDir()
    {
        new ProjectEnvironment(
            $this->homeDir,
            __DIR__.'/foobar',
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );
    }

    /**
     * @expectedException \Puli\RepositoryManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testFailIfRootDirNoDirectory()
    {
        new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir.'/file',
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );
    }

    public function testActivatePlugins()
    {
        $configFile = new ConfigFile();
        $rootPackageFile = new RootPackageFile();
        $rootPackageFile->addPluginClass('Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin');

        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue($configFile));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue($rootPackageFile));

        $environment = new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertSame($environment, TestPlugin::getEnvironment());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetRepository()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', new DefaultConfig())));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('root', $this->rootDir.'/puli.json', new DefaultConfig())));

        $environment = new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertInstanceOf('Puli\Repository\FileCopyRepository', $environment->getRepository());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetDiscovery()
    {
        $this->configFileStorage->expects($this->once())
            ->method('loadConfigFile')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue(new ConfigFile($this->homeDir.'/config.json', new DefaultConfig())));

        $this->packageFileStorage->expects($this->once())
            ->method('loadRootPackageFile')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue(new RootPackageFile('root', $this->rootDir.'/puli.json', new DefaultConfig())));

        $environment = new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFileStorage,
            $this->packageFileStorage,
            $this->dispatcher
        );

        $this->assertInstanceOf('Puli\Discovery\KeyValueStoreDiscovery', $environment->getDiscovery());
    }
}
