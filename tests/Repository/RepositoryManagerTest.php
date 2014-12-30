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

        $this->packages = new PackageCollection();

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->initEnvironment($this->tempDir.'/home', $this->tempDir.'/root');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfResourceNotFound()
    {
        $packageFile = new PackageFile('package1');
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'foobar'));

        $this->packages->add(new Package($packageFile, $this->packageDir1));

        new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceConflictException
     */
    public function testCreateFailsIfPathConflict()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path', 'resources'));

        // One of the packages  must explicitly set the other package as
        // overridden package

        $this->packages->add(new Package($packageFile1, $this->packageDir1));
        $this->packages->add(new Package($packageFile2, $this->packageDir2));

        new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceConflictException
     */
    public function testCreateFailsIfPathConflictWithNestedPath()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path/config', 'override'));

        // One of the packages  must explicitly set the other package as
        // overridden package

        $this->packages->add(new Package($packageFile1, $this->packageDir1));
        $this->packages->add(new Package($packageFile2, $this->packageDir2));

        new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceConflictException
     */
    public function testCreateFailsIfPathConflictAndPackageOrderInNonRootPackage()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path', 'override'));

        $pseudoRootConfig = new RootPackageFile('root');
        $pseudoRootConfig->setPackageOrder(array('package1', 'package2'));

        $this->packages->add(new Package($packageFile1, $this->packageDir1));
        $this->packages->add(new Package($packageFile2, $this->packageDir2));
        $this->packages->add(new Package($pseudoRootConfig, $this->packageDir3));

        new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
    }

    public function testAddResourceMappingWithDirectoryPath()
    {
        $this->initDefaultManager();

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
        $this->initDefaultManager();

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

    public function testAddResourceMappingWithMultiplePaths()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/app', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/app', new DirectoryResource($this->rootDir.'/assets'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/app', $mappings['/app']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources', 'assets'), $mappings['/app']->getFilesystemPaths());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/app', array('resources', 'assets')));
    }

    public function testAddResourceMappingWithReferenceToOtherPackage()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/package1', $mappings['/package1']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@package1:resources'), $mappings['/package1']->getFilesystemPaths());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/package1', '@package1:resources'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Repository\ResourceDefinitionException
     * @expectedExceptionMessage foobar
     */
    public function testAddResourceMappingFailsIfReferencedPackageNotFound()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('add');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addResourceMapping(new ResourceMapping('/package1', '@foobar:resources'));
    }

    public function testAddResourceMappingDoesNotFailIfNotFoundPackageOptional()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('add');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/package1', $mappings['/package1']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@?foobar:resources'), $mappings['/package1']->getFilesystemPaths());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/package1', '@?foobar:resources'));

        $this->assertTrue(true, 'No exception was thrown');
    }

    public function testAddResourceMappingOverridesConflictingPackage()
    {
        $this->repo->expects($this->once())
            ->method('add')
            ->with('/package2', new DirectoryResource($this->rootDir.'/override'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/package2', $mappings['/package2']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/package2']->getFilesystemPaths());

                // Package was added to overridden packages
                PHPUnit_Framework_Assert::assertSame(array('package1', 'package2'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->rootPackageFile->setOverriddenPackages('package1');
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package2', 'resources'));

        $this->initDefaultManager();

        $this->manager->addResourceMapping(new ResourceMapping('/package2', 'override'));
    }

    public function testAddResourceMappingOverridesMultipleConflictingPackages()
    {
        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/override'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getFilesystemPaths());

                // Only package2 was marked as overridden, because the
                // dependency between package1 and package2 is clearly defined
                PHPUnit_Framework_Assert::assertSame(array('package2'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->setOverriddenPackages('package1');

        $this->initDefaultManager();

        $this->manager->addResourceMapping(new ResourceMapping('/path', 'override'));
    }

    public function testRemoveResourceMapping()
    {
        $this->initDefaultManager();

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

    public function testRemoveResourceMappingDoesNothingIfUnknownPath()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeResourceMapping('/app');
    }

    public function testGetResourceMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/app', 'resources'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/package1', 'resources'));
        $this->packageFile2->addResourceMapping($mapping3 = new ResourceMapping('/package2', 'resources'));
        $this->packageFile3->addResourceMapping($mapping4 = new ResourceMapping('/package2', 'resources'));

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
        $this->initDefaultManager();

        $mapping1 = new ResourceMapping('/path1', 'res1');
        $mapping2 = new ResourceMapping('/path2', 'res2');

        $this->rootPackageFile->addResourceMapping($mapping1);
        $this->rootPackageFile->addResourceMapping($mapping2);

        $this->assertSame($mapping1, $this->manager->getResourceMapping('/path1'));
        $this->assertSame($mapping2, $this->manager->getResourceMapping('/path2'));
    }

    public function testHasResourceMapping()
    {
        $this->initDefaultManager();

        $this->assertFalse($this->manager->hasResourceMapping('/path'));

        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/path', 'res'));

        $this->assertTrue($this->manager->hasResourceMapping('/path'));
    }

    public function testBuildRepository()
    {
        $packageFile = new PackageFile('package');
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'resources'));
        $packageFile->addResourceMapping(new ResourceMapping('/package/css', 'assets/css'));

        $this->packages->add(new Package($packageFile, $this->packageDir1));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresPackageWithoutResources()
    {
        $packageFile = new PackageFile('package');

        $this->packages->add(new Package($packageFile, $this->packageDir1));

        $this->repo->expects($this->never())
            ->method('add');

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositoryAddsResourcesInSortedOrder()
    {
        $packageFile = new PackageFile('package');
        $packageFile->addResourceMapping(new ResourceMapping('/package/css', 'assets/css'));
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'resources'));

        $this->packages->add(new Package($packageFile, $this->packageDir1));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingExistingPackage()
    {
        $overridden = new PackageFile('package1');
        $overridden->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $overridden->addResourceMapping(new ResourceMapping('/package1/css', 'assets/css'));

        $overrider = new PackageFile('package2');
        $overrider->addResourceMapping(new ResourceMapping('/package1', 'override'));
        $overrider->addResourceMapping(new ResourceMapping('/package1/css', 'css-override'));

        // Order must be specified
        $overrider->setOverriddenPackages('package1');

        // Add overridden package first
        $this->packages->add(new Package($overridden, $this->packageDir1));
        $this->packages->add(new Package($overrider, $this->packageDir2));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(4))
            ->method('add')
            ->with('/package1/css', new DirectoryResource($this->packageDir2.'/css-override'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingFuturePackage()
    {
        $overridden = new PackageFile('package1');
        $overridden->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $overridden->addResourceMapping(new ResourceMapping('/package1/css', 'assets/css'));

        $overrider = new PackageFile('package2');
        $overrider->addResourceMapping(new ResourceMapping('/package1', 'override'));
        $overrider->addResourceMapping(new ResourceMapping('/package1/css', 'css-override'));

        // Order must be specified
        $overrider->setOverriddenPackages('package1');

        // Add overriding package first
        $this->packages->add(new Package($overrider, $this->packageDir2));
        $this->packages->add(new Package($overridden, $this->packageDir1));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(4))
            ->method('add')
            ->with('/package1/css', new DirectoryResource($this->packageDir2.'/css-override'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositorySupportsChainedOverrides()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/package1', 'override'));

        $packageFile3 = new PackageFile('package3');
        $packageFile3->addResourceMapping(new ResourceMapping('/package1', 'override2'));

        // Order must be specified
        $packageFile2->setOverriddenPackages('package1');
        $packageFile3->setOverriddenPackages('package2');

        $this->packages->add(new Package($packageFile1, $this->packageDir1));
        $this->packages->add(new Package($packageFile2, $this->packageDir2));
        $this->packages->add(new Package($packageFile3, $this->packageDir3));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir3.'/override2'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingMultiplePackages()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/package2', 'resources'));

        $packageFile3 = new PackageFile('package3');
        $packageFile3->addResourceMapping(new ResourceMapping('/package1', 'override1'));
        $packageFile3->addResourceMapping(new ResourceMapping('/package2', 'override2'));

        // Order must be specified
        $packageFile3->setOverriddenPackages(array('package1', 'package2'));

        $this->packages->add(new Package($packageFile1, $this->packageDir1));
        $this->packages->add(new Package($packageFile2, $this->packageDir2));
        $this->packages->add(new Package($packageFile3, $this->packageDir3));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package2', new DirectoryResource($this->packageDir2.'/resources'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir3.'/override1'));

        $this->repo->expects($this->at(4))
            ->method('add')
            ->with('/package2', new DirectoryResource($this->packageDir3.'/override2'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresUnknownOverriddenPackage()
    {
        $packageFile = new PackageFile('package');
        $packageFile->addResourceMapping(new ResourceMapping('/package', 'resources'));
        $packageFile->setOverriddenPackages('foobar');

        $this->packages->add(new Package($packageFile, $this->packageDir1));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package', new DirectoryResource($this->packageDir1.'/resources'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingWithMultipleDirectories()
    {
        $overridden = new PackageFile('package1');
        $overridden->addResourceMapping(new ResourceMapping('/package1', 'resources'));

        $overrider = new PackageFile('package2');
        $overrider->addResourceMapping(new ResourceMapping('/package1', array('override', 'css-override')));
        $overrider->setOverriddenPackages('package1');

        $this->packages->add(new Package($overridden, $this->packageDir1));
        $this->packages->add(new Package($overrider, $this->packageDir2));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/css-override'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingOfNestedPaths()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path/new', 'override'));

        $this->packages->add(new Package($packageFile1, $this->packageDir1));
        $this->packages->add(new Package($packageFile2, $this->packageDir2));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path/new', new DirectoryResource($this->packageDir2.'/override'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    public function testBuildRepositorySupportsPackageOrderInRootPackage()
    {
        $packageFile1 = new PackageFile('package1');
        $packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $packageFile2 = new PackageFile('package2');
        $packageFile2->addResourceMapping(new ResourceMapping('/path', 'override'));

        $rootConfig = new RootPackageFile('root');
        $rootConfig->setPackageOrder(array('package1', 'package2'));

        $this->packages->add(new Package($packageFile1, $this->packageDir1));
        $this->packages->add(new Package($packageFile2, $this->packageDir2));
        $this->packages->add(new RootPackage($rootConfig, $this->packageDir3));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir2.'/override'));

        $manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
        $manager->buildRepository();
    }

    private function initDefaultManager()
    {
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));
        $this->packages->add(new Package($this->packageFile1, $this->packageDir1));
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2));
        $this->packages->add(new Package($this->packageFile3, $this->packageDir3));

        $this->manager = new RepositoryManager($this->environment, $this->packages, $this->packageFileStorage);
    }
}
