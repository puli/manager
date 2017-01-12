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
use Puli\Manager\Api\Container;
use Puli\Manager\Api\Environment;
use Puli\Manager\Tests\Api\Fixtures\BootstrapPlugin;
use Puli\Manager\Tests\Api\Module\Fixtures\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Glob\Test\TestUtil;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @runTestsInSeparateProcesses
 */
class ContainerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $tempRoot;

    /**
     * @var string
     */
    private $tempHome;

    /**
     * @var Container
     */
    private $puli;

    protected function setUp()
    {
        $this->tempDir = TestUtil::makeTempDir('puli-manager', __CLASS__);
        $this->tempRoot = $this->tempDir.'/root';
        $this->tempHome = $this->tempDir.'/home';

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/root', $this->tempRoot);
        $filesystem->mirror(__DIR__.'/Fixtures/home', $this->tempHome);

        TestPlugin::reset();

        putenv('PULI_HOME='.$this->tempHome);

        // Make sure "HOME" (Unix)/"APPDATA" (Windows) is not set
        putenv('HOME=');
        putenv('APPDATA=');

        $this->puli = new Container();
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);

        // Unset env variables
        putenv('PULI_HOME=');
    }

    public function testPuliProtectsHomeWithGlobalContext()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        $this->puli->start();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testPuliProtectsHomeWithProjectContext()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testGetGlobalContext()
    {
        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertInstanceOf('Puli\Manager\Api\Context\Context', $context);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $context->getConfig());
        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $context->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
        $this->assertSame($this->tempHome, $context->getHomeDirectory());
        $this->assertSame($this->tempHome.'/config.json', $context->getConfigFile()->getPath());
        $this->assertSame($context->getEventDispatcher(), $this->puli->getEventDispatcher());
    }

    public function testGetGlobalContextWithoutConfigFile()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempHome.'/config.json');

        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertInstanceOf('Puli\Manager\Api\Context\Context', $context);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $context->getConfig());
        $this->assertNull($context->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
        $this->assertSame($this->tempHome, $context->getHomeDirectory());
        $this->assertSame($context->getEventDispatcher(), $this->puli->getEventDispatcher());
    }

    public function testGetGlobalContextWithEventDispatcher()
    {
        $dispatcher = new EventDispatcher();
        $this->puli->setEventDispatcher($dispatcher);
        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertSame($dispatcher, $context->getEventDispatcher());
    }

    public function testGetGlobalContextWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME=');

        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertInstanceOf('Puli\Manager\Api\Context\Context', $context);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $context->getConfig());
        $this->assertNull($context->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
        $this->assertNull($context->getHomeDirectory());
    }

    public function testGetProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertInstanceOf('Puli\Manager\Api\Context\ProjectContext', $context);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $context->getConfig());
        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFile', $context->getConfigFile());
        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModuleFile', $context->getRootModuleFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
        $this->assertSame($this->tempHome, $context->getHomeDirectory());
        $this->assertSame($this->tempRoot, $context->getRootDirectory());
        $this->assertSame($this->tempHome.'/config.json', $context->getConfigFile()->getPath());
        $this->assertSame($this->tempRoot.'/puli.json', $context->getRootModuleFile()->getPath());
        $this->assertSame($context->getEventDispatcher(), $this->puli->getEventDispatcher());
        $this->assertSame(Environment::DEV, $context->getEnvironment());
        $this->assertSame(Environment::DEV, $this->puli->getEnvironment());
    }

    public function testGetProjectContextWithoutConfigFile()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempRoot.'/puli.json');

        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertInstanceOf('Puli\Manager\Api\Context\ProjectContext', $context);
        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModuleFile', $context->getRootModuleFile());
        $this->assertSame($this->tempRoot.'/puli.json', $context->getRootModuleFile()->getPath());
    }

    public function testGetProjectContextWithEventDispatcher()
    {
        $dispatcher = new EventDispatcher();
        $this->puli->setEventDispatcher($dispatcher);
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertSame($dispatcher, $context->getEventDispatcher());
    }

    public function testGetProjectContextInProdEnvironment()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->setEnvironment(Environment::PROD);
        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertSame(Environment::PROD, $context->getEnvironment());
        $this->assertSame(Environment::PROD, $this->puli->getEnvironment());
    }

    public function testGetProjectContextWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME=');

        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $context = $this->puli->getContext();

        $this->assertInstanceOf('Puli\Manager\Api\Context\ProjectContext', $context);
        $this->assertInstanceOf('Puli\Manager\Api\Config\Config', $context->getConfig());
        $this->assertNull($context->getConfigFile());
        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModuleFile', $context->getRootModuleFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $context->getEventDispatcher());
        $this->assertNull($context->getHomeDirectory());
        $this->assertSame($this->tempRoot, $context->getRootDirectory());
        $this->assertSame($this->tempRoot.'/puli.json', $context->getRootModuleFile()->getPath());
    }

    public function testGetRepositoryInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $repo = $this->puli->getRepository();

        $this->assertInstanceOf('Puli\Repository\Api\EditableRepository', $repo);
    }

    public function testGetRepositoryInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRepository());
    }

    public function testGetDiscoveryInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $discovery = $this->puli->getDiscovery();

        $this->assertInstanceOf('Puli\Discovery\Api\EditableDiscovery', $discovery);
    }

    public function testGetDiscoveryInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getDiscovery());
    }

    public function testGetFactoryInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $factory = $this->puli->getFactory();

        $this->assertInternalType('object', $factory);
        $this->assertTrue(method_exists($factory, 'createRepository'));
    }

    public function testGetFactoryInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getFactory());
    }

    public function testGetFactoryManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getFactoryManager();

        $this->assertInstanceOf('Puli\Manager\Api\Factory\FactoryManager', $manager);
    }

    public function testGetFactoryManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getFactoryManager());
    }

    public function testGetConfigFileManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($this->puli->getContext($this->tempRoot), $manager->getContext());
    }

    public function testGetConfigFileManagerInGlobalContext()
    {
        $this->puli->start();
        $manager = $this->puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\Manager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($this->puli->getContext(), $manager->getContext());
    }

    public function testGetRootModuleFileManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getRootModuleFileManager();

        $this->assertInstanceOf('Puli\Manager\Api\Module\RootModuleFileManager', $manager);
        $this->assertSame($this->puli->getContext(), $manager->getContext());
    }

    public function testGetRootModuleFileManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRootModuleFileManager());
    }

    public function testGetModuleManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getModuleManager();

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleManager', $manager);
        $this->assertSame($this->puli->getContext(), $manager->getContext());

        $modules = $manager->getModules();

        $this->assertCount(3, $modules);
        $this->assertTrue($modules->contains('vendor/root'));
        $this->assertTrue($modules->contains('vendor/module1'));
        $this->assertTrue($modules->contains('vendor/module2'));
    }

    public function testGetModuleManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getModuleManager());
    }

    public function testGetRepositoryManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getRepositoryManager();

        $this->assertInstanceOf('Puli\Manager\Api\Repository\RepositoryManager', $manager);
        $this->assertSame($this->puli->getContext(), $manager->getContext());
    }

    public function testGetRepositoryManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRepositoryManager());
    }

    public function testGetDiscoveryManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getDiscoveryManager();

        $this->assertInstanceOf('Puli\Manager\Api\Discovery\DiscoveryManager', $manager);
        $this->assertSame($this->puli->getContext(), $manager->getContext());
    }

    public function testGetDiscoveryManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getDiscoveryManager());
    }

    public function testGetAssetManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getAssetManager();

        $this->assertInstanceOf('Puli\Manager\Api\Asset\AssetManager', $manager);
        $this->assertSame($manager, $this->puli->getAssetManager());
    }

    public function testGetAssetManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getAssetManager());
    }

    public function testGetInstallationManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getInstallationManager();

        $this->assertInstanceOf('Puli\Manager\Api\Installation\InstallationManager', $manager);
        $this->assertSame($manager, $this->puli->getInstallationManager());
    }

    public function testGetInstallationManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getInstallationManager());
    }

    public function testGetInstallerManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getInstallerManager();

        $this->assertInstanceOf('Puli\Manager\Api\Installer\InstallerManager', $manager);
        $this->assertSame($manager, $this->puli->getInstallerManager());
    }

    public function testGetInstallerManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getInstallerManager());
    }

    public function testGetServerManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getServerManager();

        $this->assertInstanceOf('Puli\Manager\Api\Server\ServerManager', $manager);
        $this->assertSame($manager, $this->puli->getServerManager());
    }

    public function testGetServerManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getServerManager());
    }

    public function testGetUrlGeneratorInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getUrlGenerator();

        $this->assertInstanceOf('Puli\UrlGenerator\Api\UrlGenerator', $manager);
        $this->assertSame($manager, $this->puli->getUrlGenerator());
    }

    public function testGetUrlGeneratorInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getUrlGenerator());
    }

    public function testPassRootDirToConstructor()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();

        $this->assertSame($this->tempRoot, $this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Context\ProjectContext', $this->puli->getContext());
        $this->assertSame($this->tempRoot, $this->puli->getContext()->getRootDirectory());
    }

    public function testPassNoRootDirToConstructor()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Context\Context', $this->puli->getContext());
    }

    public function testSetRootDirectory()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();

        $this->assertSame($this->tempRoot, $this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Context\ProjectContext', $this->puli->getContext());
        $this->assertSame($this->tempRoot, $this->puli->getContext()->getRootDirectory());
    }

    public function testPassNoRootDirToSetRootDirectory()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->setRootDirectory(null);
        $this->puli->start();

        $this->assertNull($this->puli->getRootDirectory());
        $this->assertInstanceOf('Puli\Manager\Api\Context\Context', $this->puli->getContext());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage foobar
     */
    public function testFailIfRootDirNotFound()
    {
        $this->puli->setRootDirectory($this->tempRoot.'/foobar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage puli.json
     */
    public function testFailIfRootDirNoDirectory()
    {
        $this->puli->setRootDirectory($this->tempRoot.'/puli.json');
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

    public function testActivatePluginsInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();

        $this->assertSame($this->puli, TestPlugin::getContainer());
        $this->assertSame($this->puli->getContext(), TestPlugin::getContext());
    }

    public function testDoNotActivatePluginsInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull(TestPlugin::getContainer());
        $this->assertNull(TestPlugin::getContext());
    }

    public function testDoNotActivatePluginsIfDisabled()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->disablePlugins();
        $this->puli->start();

        $this->assertNull(TestPlugin::getContainer());
        $this->assertNull(TestPlugin::getContext());
    }

    /**
     * @runInSeparateProcess
     */
    public function testUseBootstrapFileForLoadingPlugins()
    {
        $filesystem = new Filesystem();
        $filesystem->copy($this->tempRoot.'/puli-bootstrap.json', $this->tempRoot.'/puli.json', true);
        $filesystem->copy(__DIR__.'/Fixtures/test-bootstrap.php', $this->tempRoot.'/test-bootstrap.php', true);

        $this->puli->setRootDirectory($this->tempRoot);

        $this->assertFalse(defined('PULI_TEST_BOOTSTRAP_LOADED'));

        $this->puli->start();

        $this->assertTrue(defined('PULI_TEST_BOOTSTRAP_LOADED'));
        $this->assertTrue(BootstrapPlugin::$activated);
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testFailIfPluginClassNotFound()
    {
        $filesystem = new Filesystem();
        $filesystem->copy($this->tempRoot.'/puli-no-such-plugin.json', $this->tempRoot.'/puli.json', true);

        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
    }

    /**
     * @expectedException \Puli\Manager\Api\InvalidConfigException
     */
    public function testFailIfPluginClassNotInstanceOfPuliPlugin()
    {
        $filesystem = new Filesystem();
        $filesystem->copy($this->tempRoot.'/puli-not-a-plugin.json', $this->tempRoot.'/puli.json', true);

        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
    }

    public function testGetCacheManagerInProjectContext()
    {
        $this->puli->setRootDirectory($this->tempRoot);
        $this->puli->start();
        $manager = $this->puli->getCacheManager();

        $this->assertInstanceOf('Puli\Manager\Api\Cache\CacheManager', $manager);
        $this->assertSame($this->puli->getContext(), $manager->getContext());
    }

    public function testGetCacheManagerInGlobalContext()
    {
        $this->puli->start();

        $this->assertNull($this->puli->getCacheManager());
    }
}
