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
use Puli\PackageManager\Manager\ProjectConfigManager;
use Puli\PackageManager\Manager\GlobalConfigManager;
use Puli\PackageManager\Package\Config\PackageConfigStorage;
use Puli\PackageManager\Package\Config\RootPackageConfig;
use Puli\PackageManager\Tests\Manager\Fixtures\TestProjectEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectConfigManagerTest extends \PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\PackageManager\Tests\Config\Fixtures\OtherPlugin';

    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var RootPackageConfig
     */
    private $rootConfig;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageConfigStorage
     */
    private $packageConfigStorage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|GlobalConfigManager
     */
    private $globalConfigManager;

    /**
     * @var TestProjectEnvironment
     */
    private $environment;

    /**
     * @var ProjectConfigManager
     */
    private $manager;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->rootDir = __DIR__.'/Fixtures/root-package';
        $this->globalConfig = new GlobalConfig();
        $this->rootConfig = new RootPackageConfig($this->globalConfig, 'root');

        $this->packageConfigStorage = $this->getMockBuilder('Puli\PackageManager\Package\Config\PackageConfigStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->globalConfigManager = $this->getMockBuilder('Puli\PackageManager\Manager\GlobalConfigManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->globalConfig,
            $this->rootConfig,
            $this->dispatcher
        );

        $this->manager = new ProjectConfigManager(
            $this->environment,
            $this->packageConfigStorage,
            $this->globalConfigManager
        );
    }

    public function testInstallLocalPlugin()
    {
        $this->globalConfigManager->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->globalConfigManager->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->packageConfigStorage->expects($this->once())
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
        $this->globalConfigManager->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(true));

        $this->globalConfigManager->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->packageConfigStorage->expects($this->never())
            ->method('savePackageConfig');

        $this->manager->installPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(), $this->manager->getPluginClasses(false));
    }

    public function testInstallLocalDoesNothingIfPluginExistsLocally()
    {
        $this->globalConfigManager->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->globalConfigManager->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->packageConfigStorage->expects($this->never())
            ->method('savePackageConfig');

        $this->rootConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->installPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses(false));
    }

    public function testInstallGlobalPlugin()
    {
        $this->globalConfigManager->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->globalConfigManager->expects($this->once())
            ->method('installGlobalPluginClass')
            ->with(self::PLUGIN_CLASS);

        $this->packageConfigStorage->expects($this->never())
            ->method('savePackageConfig');

        $this->manager->installPluginClass(self::PLUGIN_CLASS, true);

        $this->assertSame(array(), $this->manager->getPluginClasses(false));
    }

    public function testInstallGlobalDoesNothingIfPluginExistsGlobally()
    {
        $this->globalConfigManager->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(true));

        $this->globalConfigManager->expects($this->never())
            ->method('installGlobalPluginClass');

        $this->packageConfigStorage->expects($this->never())
            ->method('savePackageConfig');

        $this->manager->installPluginClass(self::PLUGIN_CLASS, true);

        $this->assertSame(array(), $this->manager->getPluginClasses(false));
    }

    public function testInstallGlobalWritesConfigEvenThoughPluginExistsLocally()
    {
        $this->globalConfigManager->expects($this->once())
            ->method('isGlobalPluginClassInstalled')
            ->with(self::PLUGIN_CLASS)
            ->will($this->returnValue(false));

        $this->globalConfigManager->expects($this->once())
            ->method('installGlobalPluginClass')
            ->with(self::PLUGIN_CLASS);

        $this->packageConfigStorage->expects($this->never())
            ->method('savePackageConfig');

        $this->rootConfig->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->installPluginClass(self::PLUGIN_CLASS, true);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses(false));
    }

    public function testIsPluginClassInstalled()
    {
        $this->globalConfig->addPluginClass(self::PLUGIN_CLASS);
        $this->rootConfig->addPluginClass(self::OTHER_PLUGIN_CLASS);

        $this->assertTrue($this->manager->isPluginClassInstalled(self::PLUGIN_CLASS, true));
        $this->assertTrue($this->manager->isPluginClassInstalled(self::OTHER_PLUGIN_CLASS, true));
        $this->assertFalse($this->manager->isPluginClassInstalled(self::PLUGIN_CLASS, false));
        $this->assertTrue($this->manager->isPluginClassInstalled(self::OTHER_PLUGIN_CLASS, false));

        $this->assertFalse($this->manager->isPluginClassInstalled('foobar', true));
        $this->assertFalse($this->manager->isPluginClassInstalled('foobar', false));
    }
}
