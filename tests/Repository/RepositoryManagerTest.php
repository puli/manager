<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Repository;

use Puli\Repository\ResourceRepositoryInterface;
use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Config\PackageConfig;
use Puli\RepositoryManager\Package\Config\ResourceDescriptor;
use Puli\RepositoryManager\Package\Config\RootPackageConfig;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Repository\RepositoryManager;
use Puli\RepositoryManager\Tests\Package\Fixtures\TestProjectEnvironment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var string
     */
    private $package1Dir;

    /**
     * @var string
     */
    private $package2Dir;

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var RootPackageConfig
     */
    private $rootConfig;

    /**
     * @var PackageConfig
     */
    private $package1Config;

    /**
     * @var PackageConfig
     */
    private $package2Config;

    /**
     * @var TestProjectEnvironment
     */
    private $environment;

    /**
     * @var PackageCollection
     */
    private $packages;
    /**
     * @var RepositoryManager
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/RepositoryManagerTest_temp'.rand(10000, 99999), 0777, true)) {}

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->homeDir = __DIR__.'/Fixtures/home';
        $this->rootDir = __DIR__.'/Fixtures/root';
        $this->package1Dir = __DIR__.'/Fixtures/package1';
        $this->package2Dir = __DIR__.'/Fixtures/package2';

        $this->globalConfig = new GlobalConfig();
        $this->rootConfig = new RootPackageConfig($this->globalConfig, 'root');
        $this->package1Config = new PackageConfig('package1');
        $this->package2Config = new PackageConfig('package2');

        $this->initManager();
    }

    public function testDumpRepository()
    {
        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));
        $this->package1Config->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $this->package2Config->addResourceDescriptor(new ResourceDescriptor('/package2', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/cache');
        $this->assertFileExists($this->tempDir.'/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
        $this->assertSame($this->package1Dir.'/resources', $repo->get('/package1')->getLocalPath());
        $this->assertSame($this->package2Dir.'/resources', $repo->get('/package2')->getLocalPath());
    }

    public function testDumpRepositoryReplacesExistingFiles()
    {
        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        mkdir($this->tempDir.'/cache');
        touch($this->tempDir.'/cache/old');
        touch($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/cache');
        $this->assertFileExists($this->tempDir.'/repository.php');
        $this->assertFileNotExists($this->tempDir.'/cache/old');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithRelativePaths()
    {
        $filesystem = new Filesystem();
        $filesystem->mirror($this->rootDir, $this->tempDir);

        $this->rootDir = $this->tempDir;

        $this->initManager();

        $this->rootConfig->setResourceRepositoryCache('cache-dir/cache');
        $this->rootConfig->setGeneratedResourceRepository('repo-dir/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/cache-dir/cache');
        $this->assertFileExists($this->tempDir.'/repo-dir/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repo-dir/repository.php';

        $this->assertSame($this->tempDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithCustomRepositoryPath()
    {
        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository($this->tempDir.'/custom-repository.php');

        $this->assertFileExists($this->tempDir.'/cache');
        $this->assertFileExists($this->tempDir.'/custom-repository.php');
        $this->assertFileNotExists($this->tempDir.'/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/custom-repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithCustomCachePath()
    {
        $this->rootConfig->setResourceRepositoryCache($this->tempDir.'/cache');
        $this->rootConfig->setGeneratedResourceRepository($this->tempDir.'/repository.php');

        $this->rootConfig->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository(null, $this->tempDir.'/custom-cache');

        $this->assertFileExists($this->tempDir.'/custom-cache');
        $this->assertFileNotExists($this->tempDir.'/cache');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    private function initManager()
    {
        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->globalConfig,
            $this->rootConfig,
            $this->dispatcher
        );

        $this->packages = new PackageCollection(array(
            new RootPackage($this->rootConfig, $this->rootDir),
            new Package($this->package1Config, $this->package1Dir),
            new Package($this->package2Config, $this->package2Dir),
        ));

        $this->manager = new RepositoryManager($this->environment, $this->packages);
    }
}
