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
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\PackageState;
use Puli\RepositoryManager\Puli;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
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

    public function testGetGlobalEnvironment()
    {
        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\Config', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getConfigFile()->getPath());
    }

    public function testGetGlobalEnvironmentProtectsHome()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        $this->puli->getEnvironment();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testGetGlobalEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\Config', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertNull($environment->getHomeDirectory());
    }

    public function testGetProjectEnvironment()
    {
        $this->puli->setRootDir($this->tempDir);

        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\ProjectEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\Config', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\RootPackageFile', $environment->getRootPackageFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempDir, $environment->getRootDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getConfigFile()->getPath());
        $this->assertSame($this->tempDir.'/puli.json', $environment->getRootPackageFile()->getPath());
    }

    public function testGetProjectEnvironmentProtectsHome()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        $this->puli->setRootDir($this->tempDir);
        $this->puli->getEnvironment();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testGetProjectEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $this->puli->setRootDir($this->tempDir);

        $environment = $this->puli->getEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\ProjectEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\Config', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\RootPackageFile', $environment->getRootPackageFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertNull($environment->getHomeDirectory());
        $this->assertSame($this->tempDir, $environment->getRootDirectory());
        $this->assertSame($this->tempDir.'/puli.json', $environment->getRootPackageFile()->getPath());
    }

    public function testGetConfigFileManagerInProjectEnvironment()
    {
        $manager = $this->puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($this->puli->getEnvironment($this->tempDir), $manager->getEnvironment());
    }

    public function testGetConfigFileManagerInGlobalEnvironment()
    {
        $manager = $this->puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRootPackageFileManagerInProjectEnvironment()
    {
        $this->puli->setRootDir($this->tempDir);

        $manager = $this->puli->getRootPackageFileManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\RootPackageFileManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRootPackageFileManagerInGlobalEnvironment()
    {
        $this->assertNull($this->puli->getRootPackageFileManager());
    }

    public function testGetPackageManagerInProjectEnvironment()
    {
        $this->puli->setRootDir($this->tempDir);

        $manager = $this->puli->getPackageManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\PackageManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertTrue($packages->contains('vendor/root'));
        $this->assertTrue($packages->contains('vendor/package1'));
        $this->assertTrue($packages->contains('vendor/package2'));
    }

    public function testGetPackageManagerInGlobalEnvironment()
    {
        $this->assertNull($this->puli->getPackageManager());
    }

    public function testGetRepositoryManagerInProjectEnvironment()
    {
        $this->puli->setRootDir($this->tempDir);

        $manager = $this->puli->getRepositoryManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Repository\RepositoryManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRepositoryManagerInGlobalEnvironment()
    {
        $this->assertNull($this->puli->getRepositoryManager());
    }

    public function testGetDiscoveryManagerInProjectEnvironment()
    {
        $this->puli->setRootDir($this->tempDir);

        $manager = $this->puli->getDiscoveryManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Discovery\DiscoveryManager', $manager);
        $this->assertSame($this->puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetDiscoveryManagerInGlobalEnvironment()
    {
        $this->assertNull($this->puli->getDiscoveryManager());
    }
}
