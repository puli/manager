<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\ManagerFactory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ManagerFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $tempHome;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/ManagerFactoryTest_temp'.rand(10000, 99999), 0777, true)) {}
        while (false === @mkdir($this->tempHome = sys_get_temp_dir().'/puli-repo-manager/ManagerFactoryTest_home'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/root', $this->tempDir);
        $filesystem->mirror(__DIR__.'/Fixtures/home', $this->tempHome);

        putenv('PULI_HOME='.$this->tempHome);

        // Make sure "HOME" is not set
        putenv('HOME');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
        $filesystem->remove($this->tempHome);

        // Unset env variables
        putenv('PULI_HOME');
    }

    public function testCreateGlobalEnvironment()
    {
        $environment = ManagerFactory::createGlobalEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Config\Config', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getConfigFile()->getPath());
    }

    public function testCreateGlobalEnvironmentProtectsHome()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        ManagerFactory::createGlobalEnvironment();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testCreateGlobalEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $environment = ManagerFactory::createGlobalEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Config\Config', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertNull($environment->getHomeDirectory());
    }

    public function testCreateProjectEnvironment()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);

        $this->assertInstanceOf('Puli\RepositoryManager\Environment\ProjectEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Config\Config', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageFile\RootPackageFile', $environment->getRootPackageFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempDir, $environment->getRootDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getConfigFile()->getPath());
        $this->assertSame($this->tempDir.'/puli.json', $environment->getRootPackageFile()->getPath());
    }

    public function testCreateProjectEnvironmentProtectsHome()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        ManagerFactory::createProjectEnvironment($this->tempDir);

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testCreateProjectEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);

        $this->assertInstanceOf('Puli\RepositoryManager\Environment\ProjectEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Config\Config', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageFile\RootPackageFile', $environment->getRootPackageFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertNull($environment->getHomeDirectory());
        $this->assertSame($this->tempDir, $environment->getRootDirectory());
        $this->assertSame($this->tempDir.'/puli.json', $environment->getRootPackageFile()->getPath());
    }

    public function testCreateConfigFileManager()
    {
        $environment = ManagerFactory::createGlobalEnvironment();
        $manager = ManagerFactory::createConfigFileManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFileManager', $manager);
        $this->assertSame($environment, $manager->getEnvironment());
    }

    public function testCreateConfigFileManagerWithProjectEnvironment()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createConfigFileManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Config\ConfigFile\ConfigFileManager', $manager);
        $this->assertSame($environment, $manager->getEnvironment());
    }

    public function testCreateRootPackageFileManager()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createRootPackageFileManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageFile\RootPackageFileManager', $manager);
        $this->assertSame($environment, $manager->getEnvironment());
    }

    public function testCreatePackageManager()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createPackageManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Package\PackageManager', $manager);
        $this->assertSame($environment, $manager->getEnvironment());

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertSame('real-root', $packages['real-root']->getName());
        $this->assertSame('package1', $packages['package1']->getName());
        $this->assertSame('package2', $packages['package2']->getName());
    }

    public function testCreateRepositoryManager()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createRepositoryManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Repository\RepositoryManager', $manager);
    }

    public function testCreateRepositoryManagerWithPackageManager()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $packageManager = ManagerFactory::createPackageManager($environment);
        $manager = ManagerFactory::createRepositoryManager($environment, $packageManager);

        $this->assertInstanceOf('Puli\RepositoryManager\Repository\RepositoryManager', $manager);
    }

    public function testCreateDiscoveryManager()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createDiscoveryManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Discovery\DiscoveryManager', $manager);
    }

    public function testCreateDiscoveryManagerWithPackageManager()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $packageManager = ManagerFactory::createPackageManager($environment);
        $manager = ManagerFactory::createDiscoveryManager($environment, $packageManager);

        $this->assertInstanceOf('Puli\RepositoryManager\Discovery\DiscoveryManager', $manager);
    }
}
