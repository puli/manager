<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Puli;
use Puli\Manager\Tests\Api\Package\Fixtures\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @runTestsInSeparateProcesses
 */
class PuliTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $tempHome;

    /**
     * @var Puli
     */
    private $puli;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/ManagerFactoryTest_temp'.rand(10000, 99999), 0777, true)) {}
        while (false === @mkdir($this->tempHome = sys_get_temp_dir().'/puli-repo-manager/ManagerFactoryTest_home'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/root', $this->tempDir);
        $filesystem->mirror(__DIR__.'/Fixtures/home', $this->tempHome);

        TestPlugin::reset();

        putenv('PULI_HOME='.$this->tempHome);

        // Make sure "HOME" is not set
        putenv('HOME');

        $this->puli = new Puli();
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
        $filesystem->remove($this->tempHome);

        // Unset env variables
        putenv('PULI_HOME');
    }

    public function testPuliProtectsHomeWithGlobalEnvironment()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        $this->puli->start();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testPuliProtectsHomeWithProjectEnvironment()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testGetGlobalEnvironment()
    {
        $this->puli->start();
        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\Manager\Api\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $environment->getConfig());
        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getConfigFile()->getPath());
        $this->assertSame($environment->getEventDispatcher(), $this->puli->getEventDispatcher());
    }

    public function testGetGlobalEnvironmentWithEventDispatcher()
    {
        $dispatcher = new EventDispatcher();
        $this->puli->setEventDispatcher($dispatcher);
        $this->puli->start();
        $environment = $this->puli->getEnvironment();

        $this->assertSame($dispatcher, $environment->getEventDispatcher());
    }

    public function testGetGlobalEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $this->puli->start();
        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\Manager\Api\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertNull($environment->getHomeDirectory());
    }

    public function testGetProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\Manager\Api\Environment\ProjectEnvironment', $environment);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $environment->getConfig());
        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $environment->getRootPackageFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempDir, $environment->getRootDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getConfigFile()->getPath());
        $this->assertSame($this->tempDir.'/puli.json', $environment->getRootPackageFile()->getPath());
        $this->assertSame($environment->getEventDispatcher(), $this->puli->getEventDispatcher());
    }

    public function testGetProjectEnvironmentWithEventDispatcher()
    {
        $dispatcher = new EventDispatcher();
        $this->puli->setEventDispatcher($dispatcher);
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $environment = $this->puli->getEnvironment();

        $this->assertSame($dispatcher, $environment->getEventDispatcher());
    }

    public function testGetProjectEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\Manager\Api\Environment\ProjectEnvironment', $environment);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackageFile', $environment->getRootPackageFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertNull($environment->getHomeDirectory());
        $this->assertSame($this->tempDir, $environment->getRootDirectory());
        $this->assertSame($this->tempDir.'/puli.json', $environment->getRootPackageFile()->getPath());
    }

    public function testGetRepositoryInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $repo = $this->puli->getRepository();

        $this->assertInstanceOf('Puli\Repository\Api\EditableRepository', $repo);
    }

    public function testGetRepositoryInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRepository());
    }

    public function testGetDiscoveryInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $discovery = $this->puli->getDiscovery();

        $this->assertInstanceOf('Puli\Discovery\Api\EditableDiscovery', $discovery);
    }

    public function testGetDiscoveryInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getDiscovery());
    }

    public function testGetFactoryInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $factory = $this->puli->getFactory();

        $this->assertInternalType('object', $factory);
        $this->assertTrue(method_exists($factory, 'createRepository'));
    }

    public function testGetFactoryInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getFactory());
    }

    public function testGetFactoryManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getFactoryManager();

        $this->assertInstanceOf('Puli\Manager\Api\Factory\FactoryManager', $manager);
    }

    public function testGetFactoryManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getFactoryManager());
    }

    public function testGetConfigFileManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($this->puli->getEnvironment($this->tempDir), $manager->getEnvironment());
    }

    public function testGetConfigFileManagerInGlobalEnvironment()
    {
        $this->puli->start();
        $manager = $this->puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRootPackageFileManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getRootPackageFileManager();

        $this->assertInstanceOf('Puli\Manager\Api\Package\RootPackageFileManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRootPackageFileManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRootPackageFileManager());
    }

    public function testGetPackageManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getPackageManager();

        $this->assertInstanceOf('Puli\Manager\Api\Package\PackageManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertTrue($packages->contains('vendor/root'));
        $this->assertTrue($packages->contains('vendor/package1'));
        $this->assertTrue($packages->contains('vendor/package2'));
    }

    public function testGetPackageManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getPackageManager());
    }

    public function testGetRepositoryManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getRepositoryManager();

        $this->assertInstanceOf('Puli\Manager\Api\Repository\RepositoryManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRepositoryManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRepositoryManager());
    }

    public function testGetDiscoveryManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getDiscoveryManager();

        $this->assertInstanceOf('Puli\Manager\Api\Discovery\DiscoveryManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetDiscoveryManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getDiscoveryManager());
    }

    public function testGetAssetManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getAssetManager();

        $this->assertInstanceOf('Puli\Manager\Api\Asset\AssetManager', $manager);
        $this->assertSame($manager, $this->puli->getAssetManager());
    }

    public function testGetAssetManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getAssetManager());
    }

    public function testGetInstallationManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getInstallationManager();

        $this->assertInstanceOf('Puli\Manager\Api\Installation\InstallationManager', $manager);
        $this->assertSame($manager, $this->puli->getInstallationManager());
    }

    public function testGetInstallationManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getInstallationManager());
    }

    public function testGetInstallerManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getInstallerManager();

        $this->assertInstanceOf('Puli\Manager\Api\Installer\InstallerManager', $manager);
        $this->assertSame($manager, $this->puli->getInstallerManager());
    }

    public function testGetInstallerManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getInstallerManager());
    }

    public function testGetServerManagerInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getServerManager();

        $this->assertInstanceOf('Puli\Manager\Api\Server\ServerManager', $manager);
        $this->assertSame($manager, $this->puli->getServerManager());
    }

    public function testGetServerManagerInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getServerManager());
    }

    public function testGetUrlGeneratorInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
        $manager = $this->puli->getUrlGenerator();

        $this->assertInstanceOf('Puli\UrlGenerator\Api\UrlGenerator', $manager);
        $this->assertSame($manager, $this->puli->getUrlGenerator());
    }

    public function testGetUrlGeneratorInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getUrlGenerator());
    }

    public function testPassRootDirToConstructor()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();

        $this->assertSame($this->tempDir, $this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Environment\ProjectEnvironment', $this->puli->getEnvironment());
        $this->assertSame($this->tempDir, $this->puli->getEnvironment()->getRootDirectory());
    }

    public function testPassNoRootDirToConstructor()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Environment\GlobalEnvironment', $this->puli->getEnvironment());
    }

    public function testSetRootDirectory()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();

        $this->assertSame($this->tempDir, $this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Environment\ProjectEnvironment', $this->puli->getEnvironment());
        $this->assertSame($this->tempDir, $this->puli->getEnvironment()->getRootDirectory());
    }

    public function testPassNoRootDirToSetRootDirectory()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->setRootDirectory(null);
        $this->puli->start();

        $this->assertNull($this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Environment\GlobalEnvironment', $this->puli->getEnvironment());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage foobar
     */
    public function testFailIfRootDirNotFound()
    {
        $this->puli->setRootDirectory($this->tempDir.'/foobar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage puli.json
     */
    public function testFailIfRootDirNoDirectory()
    {
        $this->puli->setRootDirectory($this->tempDir.'/puli.json');
    }

    /**
     * @expectedException \Puli\Manager\Api\NoDirectoryException
     * @expectedExceptionMessage .gitkeep
     */
    public function testFailIfHomeDirNoDirectory()
    {
        putenv('PULI_HOME='.$this->tempHome.'/.gitkeep');

        $this->puli->start();
    }

    public function testActivatePluginsInProjectEnvironment()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();

        $this->assertSame($this->puli, TestPlugin::getPuli());
        $this->assertSame($this->puli->getEnvironment(), TestPlugin::getEnvironment());
    }

    public function testDoNotActivatePluginsInGlobalEnvironment()
    {
        $this->puli->start();

        $this->assertNull(TestPlugin::getPuli());
        $this->assertNull(TestPlugin::getEnvironment());
    }

    public function testDoNotActivatePluginsIfDisabled()
    {
        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->disablePlugins();
        $this->puli->start();

        $this->assertNull(TestPlugin::getPuli());
        $this->assertNull(TestPlugin::getEnvironment());
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testFailIfPluginClassNotFound()
    {
        $filesystem = new Filesystem();
        $filesystem->copy($this->tempDir.'/puli-no-such-plugin.json', $this->tempDir.'/puli.json', true);

        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testFailIfPluginClassNotInstanceOfPuliPlugin()
    {
        $filesystem = new Filesystem();
        $filesystem->copy($this->tempDir.'/puli-not-a-plugin.json', $this->tempDir.'/puli.json', true);

        $this->puli->setRootDirectory($this->tempDir);
        $this->puli->start();
    }
}
