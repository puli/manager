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
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\ResourceDescriptor;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
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
    private $packageDir1;

    /**
     * @var string
     */
    private $packageDir2;

    /**
     * @var ConfigFile
     */
    private $configFile;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var PackageFile
     */
    private $packageFile1;

    /**
     * @var PackageFile
     */
    private $packageFile2;

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
        $this->packageDir1 = __DIR__.'/Fixtures/package1';
        $this->packageDir2 = __DIR__.'/Fixtures/package2';

        $this->configFile = new ConfigFile();
        $this->rootPackageFile = new RootPackageFile('root');
        $this->packageFile1 = new PackageFile('package1');
        $this->packageFile2 = new PackageFile('package2');

        $this->initManager();
    }

    public function testDumpRepository()
    {
        $this->environment->getConfig()->set(Config::REPO_DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::REPO_DUMP_FILE, $this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));
        $this->packageFile1->addResourceDescriptor(new ResourceDescriptor('/package1', 'resources'));
        $this->packageFile2->addResourceDescriptor(new ResourceDescriptor('/package2', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/dump');
        $this->assertFileExists($this->tempDir.'/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
        $this->assertSame($this->packageDir1.'/resources', $repo->get('/package1')->getLocalPath());
        $this->assertSame($this->packageDir2.'/resources', $repo->get('/package2')->getLocalPath());
    }

    public function testDumpRepositoryReplacesExistingFiles()
    {
        $this->environment->getConfig()->set(Config::REPO_DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::REPO_DUMP_FILE, $this->tempDir.'/repository.php');

        mkdir($this->tempDir.'/dump');
        touch($this->tempDir.'/dump/old');
        touch($this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/dump');
        $this->assertFileExists($this->tempDir.'/repository.php');
        $this->assertFileNotExists($this->tempDir.'/dump/old');

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

        $this->environment->getConfig()->set(Config::REPO_DUMP_DIR, 'dump-dir/dump');
        $this->environment->getConfig()->set(Config::REPO_DUMP_FILE, 'repo-dir/repository.php');

        $this->rootPackageFile->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/dump-dir/dump');
        $this->assertFileExists($this->tempDir.'/repo-dir/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repo-dir/repository.php';

        $this->assertSame($this->tempDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithCustomRepositoryPath()
    {
        $this->environment->getConfig()->set(Config::REPO_DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::REPO_DUMP_FILE, $this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository($this->tempDir.'/custom-repository.php');

        $this->assertFileExists($this->tempDir.'/dump');
        $this->assertFileExists($this->tempDir.'/custom-repository.php');
        $this->assertFileNotExists($this->tempDir.'/repository.php');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/custom-repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithCustomCachePath()
    {
        $this->environment->getConfig()->set(Config::REPO_DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::REPO_DUMP_FILE, $this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceDescriptor(new ResourceDescriptor('/root', 'resources'));

        $this->manager->dumpRepository(null, $this->tempDir.'/custom-cache');

        $this->assertFileExists($this->tempDir.'/custom-cache');
        $this->assertFileNotExists($this->tempDir.'/dump');

        /** @var ResourceRepositoryInterface $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    private function initManager()
    {
        $this->environment = new TestProjectEnvironment(
            $this->homeDir,
            $this->rootDir,
            $this->configFile,
            $this->rootPackageFile,
            $this->dispatcher
        );

        $this->packages = new PackageCollection(array(
            new RootPackage($this->rootPackageFile, $this->rootDir),
            new Package($this->packageFile1, $this->packageDir1),
            new Package($this->packageFile2, $this->packageDir2),
        ));

        $this->manager = new RepositoryManager($this->environment, $this->packages);
    }
}
