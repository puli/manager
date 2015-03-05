<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Api;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Puli;
use Puli\RepositoryManager\Tests\Api\Package\Fixtures\TestPlugin;
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

        new Puli();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testPuliProtectsHomeWithProjectEnvironment()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        new Puli($this->tempDir);

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testGetGlobalEnvironment()
    {
        $puli = new Puli();
        $environment = $puli->getEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\Config', $environment->getConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFile', $environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getConfigFile()->getPath());
    }

    public function testGetGlobalEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $puli = new Puli();
        $environment = $puli->getEnvironment();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\Config', $environment->getConfig());
        $this->assertNull($environment->getConfigFile());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertNull($environment->getHomeDirectory());
    }

    public function testGetProjectEnvironment()
    {
        $puli = new Puli($this->tempDir);
        $environment = $puli->getEnvironment();

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

    public function testGetProjectEnvironmentWithoutHome()
    {
        // Unset env variable
        putenv('PULI_HOME');

        $puli = new Puli($this->tempDir);

        $environment = $puli->getEnvironment();

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
        $puli = new Puli($this->tempDir);
        $manager = $puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($puli->getEnvironment($this->tempDir), $manager->getEnvironment());
    }

    public function testGetConfigFileManagerInGlobalEnvironment()
    {
        $puli = new Puli();
        $manager = $puli->getConfigFileManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Config\ConfigFileManager', $manager);
        $this->assertSame($puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRootPackageFileManagerInProjectEnvironment()
    {
        $puli = new Puli($this->tempDir);
        $manager = $puli->getRootPackageFileManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\RootPackageFileManager', $manager);
        $this->assertSame($puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRootPackageFileManagerInGlobalEnvironment()
    {
        $puli = new Puli();

        $this->assertNull($puli->getRootPackageFileManager());
    }

    public function testGetPackageManagerInProjectEnvironment()
    {
        $puli = new Puli($this->tempDir);
        $manager = $puli->getPackageManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Package\PackageManager', $manager);
        $this->assertSame($puli->getEnvironment(), $manager->getEnvironment());

        $packages = $manager->getPackages();

        $this->assertCount(3, $packages);
        $this->assertTrue($packages->contains('vendor/root'));
        $this->assertTrue($packages->contains('vendor/package1'));
        $this->assertTrue($packages->contains('vendor/package2'));
    }

    public function testGetPackageManagerInGlobalEnvironment()
    {
        $puli = new Puli();

        $this->assertNull($puli->getPackageManager());
    }

    public function testGetRepositoryManagerInProjectEnvironment()
    {
        $puli = new Puli($this->tempDir);
        $manager = $puli->getRepositoryManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Repository\RepositoryManager', $manager);
        $this->assertSame($puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetRepositoryManagerInGlobalEnvironment()
    {
        $puli = new Puli();

        $this->assertNull($puli->getRepositoryManager());
    }

    public function testGetDiscoveryManagerInProjectEnvironment()
    {
        $puli = new Puli($this->tempDir);
        $manager = $puli->getDiscoveryManager();

        $this->assertInstanceOf('Puli\RepositoryManager\Api\Discovery\DiscoveryManager', $manager);
        $this->assertSame($puli->getEnvironment(), $manager->getEnvironment());
    }

    public function testGetDiscoveryManagerInGlobalEnvironment()
    {
        $puli = new Puli();

        $this->assertNull($puli->getDiscoveryManager());
    }

    public function testPassRootDirToConstructor()
    {
        $puli = new Puli($this->tempDir);

        $this->assertSame($this->tempDir, $puli->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\ProjectEnvironment', $puli->getEnvironment());
        $this->assertSame($this->tempDir, $puli->getEnvironment()->getRootDirectory());
    }

    public function testPassNoRootDirToConstructor()
    {
        $puli = new Puli();

        $this->assertNull($puli->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\GlobalEnvironment', $puli->getEnvironment());
    }

    public function testSetRootDirectory()
    {
        $puli = new Puli();
        $puli->setRootDirectory($this->tempDir);

        $this->assertSame($this->tempDir, $puli->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\ProjectEnvironment', $puli->getEnvironment());
        $this->assertSame($this->tempDir, $puli->getEnvironment()->getRootDirectory());
    }

    public function testPassNoRootDirToSetRootDirectory()
    {
        $puli = new Puli($this->tempDir);
        $puli->setRootDirectory(null);

        $this->assertNull($puli->getRootDirectory());
        $this->assertInstanceOf('Puli\RepositoryManager\Api\Environment\GlobalEnvironment', $puli->getEnvironment());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\FileNotFoundException
     * @expectedExceptionMessage foobar
     */
    public function testFailIfRootDirNotFound()
    {
        new Puli($this->tempDir.'/foobar');
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\NoDirectoryException
     * @expectedExceptionMessage puli.json
     */
    public function testFailIfRootDirNoDirectory()
    {
        new Puli($this->tempDir.'/puli.json');
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\NoDirectoryException
     * @expectedExceptionMessage .gitkeep
     */
    public function testFailIfHomeDirNoDirectory()
    {
        putenv('PULI_HOME='.$this->tempHome.'/.gitkeep');

        new Puli();
    }

    public function testActivatePluginsInProjectEnvironment()
    {
        $puli = new Puli($this->tempDir);

        $this->assertSame($puli, TestPlugin::getPuli());
    }

    public function testDoNotActivatePluginsInGlobalEnvironment()
    {
        new Puli();

        $this->assertNull(TestPlugin::getPuli());
    }
}
