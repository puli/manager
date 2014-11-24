<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Tests\Manager;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\Config\GlobalConfigStorage;
use Puli\PackageManager\Manager\ProjectEnvironment;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Tests\Config\Fixtures\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironmentTest extends \PHPUnit_Framework_TestCase
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
     * @var \PHPUnit_Framework_MockObject_MockObject|GlobalConfigStorage
     */
    private $globalConfigStorage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigStorage
     */
    private $packageConfigStorage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    protected function setUp()
    {
        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->rootDir = __DIR__.'/Fixtures/root-package';
        $this->globalConfigStorage = $this->getMockBuilder('Puli\PackageManager\Config\GlobalConfigStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->packageConfigStorage = $this->getMockBuilder('Puli\PackageManager\Package\Config\PackageConfigStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    public function testCreate()
    {
        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig);

        $this->globalConfigStorage->expects($this->once())
            ->method('loadGlobalConfig')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue($globalConfig));

        $this->packageConfigStorage->expects($this->once())
            ->method('loadRootPackageConfig')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue($rootConfig));

        $environment = new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->globalConfigStorage,
            $this->packageConfigStorage,
            $this->dispatcher
        );

        $this->assertSame($this->homeDir, $environment->getHomeDirectory());
        $this->assertSame($this->rootDir, $environment->getRootDirectory());
        $this->assertSame($globalConfig, $environment->getGlobalConfig());
        $this->assertSame($rootConfig, $environment->getProjectConfig());
        $this->assertSame($this->dispatcher, $environment->getEventDispatcher());
    }

    /**
     * @expectedException \Puli\PackageManager\FileNotFoundException
     * @expectedExceptionMessage /foobar
     */
    public function testFailIfNonExistingRootDir()
    {
        new ProjectEnvironment(
            $this->homeDir,
            __DIR__.'/foobar',
            $this->globalConfigStorage,
            $this->packageConfigStorage,
            $this->dispatcher
        );
    }

    /**
     * @expectedException \Puli\PackageManager\NoDirectoryException
     * @expectedExceptionMessage /file
     */
    public function testFailIfRootDirNoDirectory()
    {
        new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir.'/file',
            $this->globalConfigStorage,
            $this->packageConfigStorage,
            $this->dispatcher
        );
    }

    public function testActivatePlugins()
    {
        $globalConfig = new GlobalConfig();
        $rootConfig = new RootPackageConfig($globalConfig);
        $rootConfig->addPluginClass('Puli\PackageManager\Tests\Config\Fixtures\TestPlugin');

        $this->globalConfigStorage->expects($this->once())
            ->method('loadGlobalConfig')
            ->with($this->homeDir.'/config.json')
            ->will($this->returnValue($globalConfig));

        $this->packageConfigStorage->expects($this->once())
            ->method('loadRootPackageConfig')
            ->with($this->rootDir.'/puli.json')
            ->will($this->returnValue($rootConfig));

        $environment = new ProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->globalConfigStorage,
            $this->packageConfigStorage,
            $this->dispatcher
        );

        $this->assertSame($environment, TestPlugin::getEnvironment());
    }
}
