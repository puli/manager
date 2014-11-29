<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Package\PackageFile;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileManager;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Tests\Package\Fixtures\TestProjectEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileManagerTest extends \PHPUnit_Framework_TestCase
{
    const PLUGIN_CLASS = 'Puli\RepositoryManager\Tests\Package\PackageFile\Fixtures\TestPlugin';

    const OTHER_PLUGIN_CLASS = 'Puli\RepositoryManager\Tests\Config\Fixtures\OtherPlugin';

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
    private $configFile;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var TestProjectEnvironment
     */
    private $environment;

    /**
     * @var PackageFileManager
     */
    private $manager;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->rootDir = __DIR__.'/Fixtures/root';
        $this->configFile = new ConfigFile();
        $this->rootPackageFile = new RootPackageFile('root');

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFile,
            $this->rootPackageFile,
            $this->dispatcher
        );

        $this->manager = new PackageFileManager($this->environment, $this->packageFileStorage);
    }

    public function testSetConfigKey()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                \PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
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

                \PHPUnit_Framework_Assert::assertSame('my-puli-dir', $config->get(Config::PULI_DIR));
                \PHPUnit_Framework_Assert::assertSame('my-puli-dir/my-install-file.json', $config->get(Config::INSTALL_FILE));
            }));

        $this->manager->setConfigKeys(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::INSTALL_FILE => '{$puli-dir}/my-install-file.json',
        ));
    }

    public function testGetConfigKey()
    {
        $this->assertNull($this->manager->getConfigKey(Config::PULI_DIR));

        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');

        $this->assertSame('my-puli-dir', $this->manager->getConfigKey(Config::PULI_DIR));
    }

    public function testGetConfigKeyReturnsRawValue()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::INSTALL_FILE, '{$puli-dir}/install-file.json');

        $this->assertSame('{$puli-dir}/install-file.json', $this->manager->getConfigKey(Config::INSTALL_FILE));
    }

    public function testGetConfigKeys()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::INSTALL_FILE, '{$puli-dir}/install-file.json');

        $this->assertSame(array(
            Config::PULI_DIR => 'my-puli-dir',
            Config::INSTALL_FILE => '{$puli-dir}/install-file.json',
        ), $this->manager->getConfigKeys());
    }

    public function testRemoveConfigKey()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::INSTALL_FILE, 'install-file.json');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                \PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, false));
                \PHPUnit_Framework_Assert::assertSame('install-file.json', $config->get(Config::INSTALL_FILE, false));
            }));

        $this->manager->removeConfigKey(Config::PULI_DIR);
    }

    public function testRemoveConfigKeys()
    {
        $this->rootPackageFile->getConfig()->set(Config::PULI_DIR, 'my-puli-dir');
        $this->rootPackageFile->getConfig()->set(Config::INSTALL_FILE, 'install-file.json');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                $config = $packageFile->getConfig();

                \PHPUnit_Framework_Assert::assertNull($config->get(Config::PULI_DIR, false));
                \PHPUnit_Framework_Assert::assertNull($config->get(Config::INSTALL_FILE, false));
            }));

        $this->manager->removeConfigKeys(array(Config::PULI_DIR, Config::INSTALL_FILE));
    }

    public function testInstallPlugin()
    {
        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->isInstanceOf('Puli\RepositoryManager\Package\PackageFile\RootPackageFile'))
            ->will($this->returnCallback(function (RootPackageFile $packageFile) {
                \PHPUnit_Framework_Assert::assertSame(array(PackageFileManagerTest::PLUGIN_CLASS), $packageFile->getPluginClasses());
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
