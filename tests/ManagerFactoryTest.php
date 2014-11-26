<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests;

use Puli\RepositoryManager\ManagerFactory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ManagerFactoryTest extends \PHPUnit_Framework_TestCase
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

        $this->assertInstanceOf('Puli\RepositoryManager\Config\GlobalEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Config\GlobalConfig', $environment->getGlobalConfig());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getGlobalConfig()->getPath());
    }

    public function testCreateGlobalEnvironmentProtectsHome()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        ManagerFactory::createGlobalEnvironment();

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testCreateProjectEnvironment()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);

        $this->assertInstanceOf('Puli\RepositoryManager\Project\ProjectEnvironment', $environment);
        $this->assertInstanceOf('Puli\RepositoryManager\Config\GlobalConfig', $environment->getGlobalConfig());
        $this->assertInstanceOf('Puli\RepositoryManager\Package\Config\RootPackageConfig', $environment->getRootPackageConfig());
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcherInterface', $environment->getEventDispatcher());
        $this->assertSame($this->tempHome, $environment->getHomeDirectory());
        $this->assertSame($this->tempDir, $environment->getRootDirectory());
        $this->assertSame($this->tempHome.'/config.json', $environment->getGlobalConfig()->getPath());
        $this->assertSame($this->tempDir.'/puli.json', $environment->getRootPackageConfig()->getPath());
    }

    public function testCreateProjectEnvironmentProtectsHome()
    {
        $this->assertFileNotExists($this->tempHome.'/.htaccess');

        ManagerFactory::createProjectEnvironment($this->tempDir);

        $this->assertFileExists($this->tempHome.'/.htaccess');
        $this->assertSame('Deny from all', file_get_contents($this->tempHome.'/.htaccess'));
    }

    public function testCreateGlobalConfigManager()
    {
        $environment = ManagerFactory::createGlobalEnvironment();
        $manager = ManagerFactory::createGlobalConfigManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Config\GlobalConfigManager', $manager);
        $this->assertSame($environment, $manager->getEnvironment());
    }

    public function testCreateGlobalConfigManagerWithProjectEnvironment()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createGlobalConfigManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Config\GlobalConfigManager', $manager);
        $this->assertSame($environment, $manager->getEnvironment());
    }

    public function testCreateProjectConfigManager()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createProjectConfigManager($environment);

        $this->assertInstanceOf('Puli\RepositoryManager\Project\ProjectConfigManager', $manager);
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

    public function testCreateResourceRepository()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $repo = ManagerFactory::createRepository($environment);

        $this->assertInstanceOf('Puli\Repository\ResourceRepositoryInterface', $repo);
    }

    public function testCreateResourceRepositoryIfAlreadyGenerated()
    {
        $environment = ManagerFactory::createProjectEnvironment($this->tempDir);
        $manager = ManagerFactory::createRepositoryManager($environment);
        $manager->dumpRepository();
        $repo = ManagerFactory::createRepository($environment);

        $this->assertInstanceOf('Puli\Repository\ResourceRepositoryInterface', $repo);
    }
}
