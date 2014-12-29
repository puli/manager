<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Package\PackageFile\PackageFileManager;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Tests\ManagerTestCase;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileManagerTest extends ManagerTestCase
{
    const PLUGIN_CLASS = 'Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\RepositoryManager\Tests\Config\Fixtures\OtherPlugin';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PackageFileManager
     */
    private $manager;

    protected function setUp()
    {
        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->initEnvironment(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');

        $this->manager = new PackageFileManager($this->environment, $this->packageFileStorage);
    }

    public function testSetConfigKey()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
            }));

        $this->manager->setConfigKey(Config::PULI_DIR, 'my-puli-dir');
    }

    public function testSetConfigKeys()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
                PHPUnit_Framework_Assert::assertSame('my-puli-dir/my-MyServiceRegistry.php', $config->get(Config::FACTORY_FILE));
            }));

        $this->manager->setConfigKeys(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_FILE => '{$puli-dir}/my-MyServiceRegistry.php',
        ));
    }

    public function testGetConfigKey()
    {
        $this->assertNull($this->manager->getConfigKey(Config::PULI_DIR));

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR));
    }

    public function testGetConfigKeyReturnsDefault()
    {
        $this->assertNull($this->manager->getConfigKey(Config::PULI_DIR));

        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR, 'my-puli-dir'));
    }

    public function testGetConfigKeyReturnsRawValue()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyServiceRegistry.php');

        $this->assertSame('{$puli-dir}/MyServiceRegistry.php', $this->manager->getConfigKey(Config::FACTORY_FILE));
    }

    public function testGetConfigKeys()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, '{$puli-dir}/MyServiceRegistry.php');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::FACTORY_FILE => '{$puli-dir}/MyServiceRegistry.php',
        ), $this->manager->getConfigKeys());
    }

    public function testRemoveConfigKey()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, 'MyServiceRegistry.php');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertSame('MyServiceRegistry.php', $config->get(Config::FACTORY_FILE, null, false));
            }));

        $this->manager->removeConfigKey(Config::PULI_DIR);
    }

    public function testRemoveConfigKeys()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::FACTORY_FILE, 'MyServiceRegistry.php');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, null, false));
                PHPUnit_Framework_Assert::assertNull($config->get(Config::FACTORY_FILE, null, false));
            }));

        $this->manager->removeConfigKeys(array(Config::PULI_DIR, Config::FACTORY_FILE));
    }

    public function testInstallPlugin()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->isInstanceOf('Puli\RepositoryManager\Package\PackageFile\RootPackageFile'))
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                PHPUnit_Framework_Assert::assertSame(array(PackageFileManagerTest::PLUGIN_CLASS), $packageFile->getPluginClasses());
            }));

        $this->manager->installPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }


    public function testInstallPluginDoesNothingIfAlreadyInstalled()
    {
        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->manager->installPluginClass(self::PLUGIN_CLASS);

        $this->assertSame(array(self::PLUGIN_CLASS), $this->manager->getPluginClasses());
    }

    public function testIsPluginClassInstalled()
    {
        $this->rootPackageFile->addPluginClass(self::PLUGIN_CLASS);

        $this->assertTrue($this->manager->isPluginClassInstalled(self::PLUGIN_CLASS));
        $this->assertFalse($this->manager->isPluginClassInstalled(self::OTHER_PLUGIN_CLASS));

        $this->assertFalse($this->manager->isPluginClassInstalled('foobar'));
    }
}
