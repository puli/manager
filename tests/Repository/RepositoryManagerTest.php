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

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Puli\Repository\ResourceRepository;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Repository\RepositoryManager;
use Puli\RepositoryManager\Repository\ResourceMapping;
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
     * @var string
     */
    private $packageDir3;

    /**
     * @var PackageFile
     */
    private $packageFile1;

    /**
     * @var PackageFile
     */
    private $packageFile2;

    /**
     * @var PackageFile
     */
    private $packageFile3;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

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
        $this->packageDir3 = $this->tempDir.'/package3';

        $this->packageFile1 = new PackageFile('package1');
        $this->packageFile2 = new PackageFile('package2');
        $this->packageFile3 = new PackageFile('package3');

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->initEnvironment($this->tempDir.'/home', $this->tempDir.'/root');
        $this->initManager();
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testAddResourceMappingWithDirectoryPath()
    {
        $this->repo->expects($this->once())
            ->method('add')
            ->with('/app', new DirectoryResource($this->rootDir.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/app', $mappings['/app']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources'), $mappings['/app']->getFilesystemPaths());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/app', 'resources'));
    }

    public function testAddResourceMappingWithFilePath()
    {
        $this->repo->expects($this->once())
            ->method('add')
            ->with('/app/file', new FileResource($this->rootDir.'/resources/file'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/app/file', $mappings['/app/file']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources/file'), $mappings['/app/file']->getFilesystemPaths());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/app/file', 'resources/file'));
    }

    public function testRemoveResourceMapping()
    {
        // TODO add overridden path from package
        $this->repo->expects($this->once())
            ->method('remove')
            ->with('/app');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/app', 'resources'));

        $this->manager->removeResourceMapping('/app');
    }

    public function testGetResourceMappings()
    {
        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/app', 'resources'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/package1', 'resources'));
        $this->packageFile2->addResourceMapping($mapping3 = new ResourceMapping('/package2', 'resources'));
        $this->packageFile3->addResourceMapping($mapping4 = new ResourceMapping('/package2', 'resources'));

        $this->initManager();

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
        ), $this->manager->getResourceMappings());

        $this->assertSame(array($mapping2), $this->manager->getResourceMappings('package1'));
        $this->assertSame(array($mapping2, $mapping3), $this->manager->getResourceMappings(array('package1', 'package2')));
    }

    public function testGetResourceMapping()
    {
        $mapping1 = new ResourceMapping('/path1', 'res1');
        $mapping2 = new ResourceMapping('/path2', 'res2');

        $this->rootPackageFile->addResourceMapping($mapping1);
        $this->rootPackageFile->addResourceMapping($mapping2);

        $this->assertSame($mapping1, $this->manager->getResourceMapping('/path1'));
        $this->assertSame($mapping2, $this->manager->getResourceMapping('/path2'));
    }

    public function testHasResourceMapping()
    {
        $this->assertFalse($this->manager->hasResourceMapping('/path'));

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/path', 'res'));

        $this->assertTrue($this->manager->hasResourceMapping('/path'));
    }

    public function testBuildRepository()
    {
        return $this->markTestIncomplete('Not working at the moment');

        $this->environment->getConfig()->set(Config::REPOSITORY_TYPE, 'filesystem');
        $this->environment->getConfig()->set(Config::REPOSITORY_PATH, 'repository');
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
            new Package($this->packageFile3, $this->packageDir3),
        ));

        $this->manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
    }
}
