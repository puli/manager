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
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironmentTest extends PHPUnit_Framework_TestCase
{
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
        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->rootDir = __DIR__.'/Fixtures/root';
        $this->configFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    public function testCreate()
    {
        $configFile = new ConfigFile();
        $rootPackageFile = new RootPackageFile();

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

        $this->assertSame($this->homeDir, $environment->getHomeDirectory());
        $this->assertSame($this->rootDir, $environment->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\EnvConfig', $environment->getConfig());
        $this->assertSame($configFile, $environment->getConfigFile());
        $this->assertSame($rootPackageFile, $environment->getRootPackageFile());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());
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
}
