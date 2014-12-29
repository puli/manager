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
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/RepositoryManagerTest'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->packageDir1 = $this->tempDir.'/package1';
        $this->packageDir2 = $this->tempDir.'/package2';

        $this->packageFile1 = new PackageFile('package1');
        $this->packageFile2 = new PackageFile('package2');

        $this->initEnvironment($this->tempDir.'/home', $this->tempDir.'/root');
        $this->initManager();
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testBuildRepository()
    {
        $this->environment->getConfig()->set(Config::REPO_TYPE, 'filesystem');
        $this->environment->getConfig()->set(Config::REPO_STORAGE_DIR, 'repository');
        $this->environment->getConfig()->set(Config::DISCOVERY_TYPE, 'key-value-store');

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/root', 'resources'));
        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package2', 'resources'));

        $this->manager->buildRepository();

        $repo = $this->environment->getRepository();

        $this->assertSame($this->rootDir.'/repository/root', $repo->get('/root')->getFilesystemPath());
        $this->assertSame($this->rootDir.'/repository/package1', $repo->get('/package1')->getFilesystemPath());
        $this->assertSame($this->rootDir.'/repository/package1/config/config.yml', $repo->get('/package1/config/config.yml')->getFilesystemPath());
        $this->assertSame($this->rootDir.'/repository/package2', $repo->get('/package2')->getFilesystemPath());
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
