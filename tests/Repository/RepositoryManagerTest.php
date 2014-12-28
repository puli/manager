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

use Puli\Repository\ResourceRepository;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\ResourceMapping;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Repository\RepositoryManager;
use Puli\RepositoryManager\Tests\ManagerTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManagerTest extends ManagerTestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $packageDir1;

    /**
     * @var string
     */
    private $packageDir2;

    /**
     * @var PackageFile
     */
    private $packageFile1;

    /**
     * @var PackageFile
     */
    private $packageFile2;

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

        $this->packageDir1 = __DIR__.'/Fixtures/package1';
        $this->packageDir2 = __DIR__.'/Fixtures/package2';

        $this->packageFile1 = new PackageFile('package1');
        $this->packageFile2 = new PackageFile('package2');

        $this->initEnvironment(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');
        $this->initManager();
    }

    public function testDumpRepository()
    {
        return $this->markTestIncomplete('Does not work right now.');

        $this->environment->getConfig()->set(Config::DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::WRITE_REPO, $this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/root', 'resources'));
        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package2', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/dump');
        $this->assertFileExists($this->tempDir.'/repository.php');

        /** @var ResourceRepository $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
        $this->assertSame($this->packageDir1.'/resources', $repo->get('/package1')->getLocalPath());
        $this->assertSame($this->packageDir2.'/resources', $repo->get('/package2')->getLocalPath());
    }

    public function testDumpRepositoryReplacesExistingFiles()
    {
        return $this->markTestIncomplete('Does not work right now.');

        $this->environment->getConfig()->set(Config::DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::WRITE_REPO, $this->tempDir.'/repository.php');

        mkdir($this->tempDir.'/dump');
        touch($this->tempDir.'/dump/old');
        touch($this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/root', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/dump');
        $this->assertFileExists($this->tempDir.'/repository.php');
        $this->assertFileNotExists($this->tempDir.'/dump/old');

        /** @var ResourceRepository $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithRelativePaths()
    {
        return $this->markTestIncomplete('Does not work right now.');

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/root', $this->tempDir);

        $this->initEnvironment(__DIR__.'/Fixtures/home', $this->tempDir);
        $this->initManager();

        $this->environment->getConfig()->set(Config::DUMP_DIR, 'dump-dir/dump');
        $this->environment->getConfig()->set(Config::WRITE_REPO, 'repo-dir/repository.php');

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/root', 'resources'));

        $this->manager->dumpRepository();

        $this->assertFileExists($this->tempDir.'/dump-dir/dump');
        $this->assertFileExists($this->tempDir.'/repo-dir/repository.php');

        /** @var ResourceRepository $repo */
        $repo = require $this->tempDir.'/repo-dir/repository.php';

        $this->assertSame($this->tempDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithCustomRepositoryPath()
    {
        return $this->markTestIncomplete('Does not work right now.');

        $this->environment->getConfig()->set(Config::DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::WRITE_REPO, $this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/root', 'resources'));

        $this->manager->dumpRepository($this->tempDir.'/custom-repository.php');

        $this->assertFileExists($this->tempDir.'/dump');
        $this->assertFileExists($this->tempDir.'/custom-repository.php');
        $this->assertFileNotExists($this->tempDir.'/repository.php');

        /** @var ResourceRepository $repo */
        $repo = require $this->tempDir.'/custom-repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    public function testDumpRepositoryWithCustomCachePath()
    {
        return $this->markTestIncomplete('Does not work right now.');

        $this->environment->getConfig()->set(Config::DUMP_DIR, $this->tempDir.'/dump');
        $this->environment->getConfig()->set(Config::WRITE_REPO, $this->tempDir.'/repository.php');

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/root', 'resources'));

        $this->manager->dumpRepository(null, $this->tempDir.'/custom-cache');

        $this->assertFileExists($this->tempDir.'/custom-cache');
        $this->assertFileNotExists($this->tempDir.'/dump');

        /** @var ResourceRepository $repo */
        $repo = require $this->tempDir.'/repository.php';

        $this->assertSame($this->rootDir.'/resources', $repo->get('/root')->getLocalPath());
    }

    private function initManager()
    {
        $this->packages = new PackageCollection(array(
            new RootPackage($this->rootPackageFile, $this->rootDir),
            new Package($this->packageFile1, $this->packageDir1),
            new Package($this->packageFile2, $this->packageDir2),
        ));

        $this->manager = new RepositoryManager($this->environment, $this->packages);
    }
}
