<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Repository;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Repository\ResourceMapping;
use Puli\Manager\Api\Repository\ResourceMappingState;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Repository\RepositoryManagerImpl;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Manager\Tests\TestException;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepositoryManagerImplTest extends ManagerTestCase
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
     * @var RepositoryManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/RepositoryManagerImplTest'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->packageDir1 = $this->tempDir.'/package1';
        $this->packageDir2 = $this->tempDir.'/package2';
        $this->packageDir3 = $this->tempDir.'/package3';

        $this->packageFile1 = new PackageFile('vendor/package1');
        $this->packageFile2 = new PackageFile('vendor/package2');
        $this->packageFile3 = new PackageFile('vendor/package3');

        $this->packages = new PackageCollection();

        $this->packageFileStorage = $this->getMockBuilder('Puli\Manager\Package\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->initEnvironment($this->tempDir.'/home', $this->tempDir.'/root');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testAddResourceMappingWithDirectoryPath()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/path', 'resources'));
    }

    public function testAddResourceMappingWithFilePath()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path/file', new FileResource($this->rootDir.'/resources/file'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path/file', $mappings['/path/file']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources/file'), $mappings['/path/file']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path/file']->isEnabled());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/path/file', 'resources/file'));
    }

    public function testAddResourceMappingWithMultiplePaths()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/assets'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources', 'assets'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/path', array('resources', 'assets')));
    }

    public function testAddResourceMappingWithReferenceToOtherPackage()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@vendor/package1:resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/path', '@vendor/package1:resources'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NoSuchPackageException
     * @expectedExceptionMessage foobar
     */
    public function testAddResourceMappingFailsIfReferencedPackageNotFound()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->never())
            ->method('add');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addResourceMapping(new ResourceMapping('/path', '@foobar:resources'));
    }

    public function testAddResourceMappingDoesNotFailIfReferencedPackageNotFoundAndNotFailIfNotFound()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->never())
            ->method('add');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@foobar:resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isNotFound());
            }));

        $this->manager->addResourceMapping(new ResourceMapping('/path', '@foobar:resources'), false);
    }

    public function testAddResourceMappingOverridesConflictingPackage()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

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
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());

                // Package was added to overridden packages
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1', 'vendor/package2'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $this->manager->addResourceMapping(new ResourceMapping('/path', 'override'));

        // No conflict was added
        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testAddResourceMappingOverridesMultipleConflictingPackages()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());

                // Only package2 was marked as overridden, because the
                // dependency between package1 and package2 is clearly defined
                PHPUnit_Framework_Assert::assertSame(array('vendor/package2'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));

        $this->manager->addResourceMapping(new ResourceMapping('/path', 'resources'));
    }

    public function testAddResourceMappingWithConflictDoesNotChangeExistingConflicts()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

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
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        // Old conflict
        $this->packageFile1->addResourceMapping(new ResourceMapping('/old', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/old', 'resources'));

        $this->manager->addResourceMapping(new ResourceMapping('/path', 'override'));

        // Old conflict still exists
        $conflicts = $this->manager->getPathConflicts();
        $this->assertCount(1, $conflicts);
        $this->assertSame('/old', $conflicts[0]->getRepositoryPath());
    }

    public function testAddResourceMappingOverridesNestedPath1()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

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
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path/config', 'resources'));

        // /override overrides /package1/resources/config
        $this->manager->addResourceMapping(new ResourceMapping('/path', 'override'));
    }

    public function testAddResourceMappingOverridesNestedPath2()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path/config', new DirectoryResource($this->rootDir.'/override'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path/config', $mappings['/path/config']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path/config']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path/config']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        // /override overrides /package1/resources/config
        $this->manager->addResourceMapping(new ResourceMapping('/path/config', 'override'));
    }

    public function testAddResourceMappingRestoresOverriddenResourcesIfSavingFails()
    {
        $this->initDefaultManager();

        $mapping = new ResourceMapping('/path', 'resources');

        $this->packageFile1->addResourceMapping($existing = new ResourceMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException('Cannot save'));

        try {
            $this->manager->addResourceMapping($mapping);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($existing->isEnabled());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testAddResourceMappingRemovesNewConflictsIfSavingFails()
    {
        $this->initDefaultManager();

        $mapping = new ResourceMapping('/path', 'resources');

        $this->packageFile1->addResourceMapping($existing = new ResourceMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException('Cannot save'));

        try {
            $this->manager->addResourceMapping($mapping);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($existing->isEnabled());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testRemoveResourceMapping()
    {
        $this->initDefaultManager();

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

        $this->rootPackageFile->addResourceMapping($mapping = new ResourceMapping('/app', 'resources'));

        $this->manager->removeResourceMapping('/app');

        $this->assertFalse($mapping->isLoaded());
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

    public function testRemoveResourceMappingRestoresOverriddenResource()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/package1');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeResourceMapping('/package1');
    }

    public function testRemoveResourceMappingRestoresOverriddenNestedResource1()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override overrides /package1/resources/config
        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/path/config', 'override'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeResourceMapping('/path/config');
    }

    public function testRemoveResourceMappingRestoresOverriddenNestedResource2()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path/config', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override/config overrides /package1/resources
        $this->packageFile1->addResourceMapping(new ResourceMapping('/path/config', 'resources'));
        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/path', 'override'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeResourceMapping('/path');
    }

    public function testRemoveResourceMappingDoesNotRestoreOverriddenNestedResourceIfNotEnabled()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->once())
            ->method('remove')
            ->with('/path/config');

        $this->repo->expects($this->never())
            ->method('add');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override overrides /package1/resources/config
        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/path/config', 'override'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeResourceMapping('/path/config');
    }

    public function testRemoveResourceRemovesResolvedConflicts()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getResourceMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->rootPackageFile->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $this->manager->removeResourceMapping('/path');

        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testGetAllResourceMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/path1', 'resources'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/path2', 'resources'));
        $this->packageFile2->addResourceMapping($mapping3 = new ResourceMapping('/path2', 'resources'));
        $this->packageFile3->addResourceMapping($mapping4 = new ResourceMapping('/path3', 'foobar'));

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
        ), $this->manager->getResourceMappings());

        $this->assertSame(array($mapping2), $this->manager->getResourceMappings('vendor/package1'));
        $this->assertSame(array($mapping2, $mapping3), $this->manager->getResourceMappings(array('vendor/package1', 'vendor/package2')));
    }

    public function testGetEnabledResourceMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/path1', 'resources'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/path2', 'resources'));
        $this->packageFile2->addResourceMapping($mapping3 = new ResourceMapping('/path3', 'resources'));
        $this->packageFile3->addResourceMapping($mapping4 = new ResourceMapping('/path4', 'resources'));
        $this->packageFile3->addResourceMapping($mapping5 = new ResourceMapping('/path5', 'foobar'));

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
        ), $this->manager->getResourceMappings(null, ResourceMappingState::ENABLED));

        $this->assertSame(array($mapping2), $this->manager->getResourceMappings('vendor/package1', ResourceMappingState::ENABLED));
        $this->assertSame(array($mapping2, $mapping3), $this->manager->getResourceMappings(array('vendor/package1', 'vendor/package2'), ResourceMappingState::ENABLED));
    }

    public function testGetNotFoundMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/path1', 'foobar'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/path2', 'foobar'));
        $this->packageFile2->addResourceMapping($mapping3 = new ResourceMapping('/path3', 'foobar'));
        $this->packageFile3->addResourceMapping($mapping4 = new ResourceMapping('/path4', 'foobar'));
        $this->packageFile3->addResourceMapping($mapping5 = new ResourceMapping('/path5', 'resources'));

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
        ), $this->manager->getResourceMappings(null, ResourceMappingState::NOT_FOUND));

        $this->assertSame(array($mapping2), $this->manager->getResourceMappings('vendor/package1', ResourceMappingState::NOT_FOUND));
        $this->assertSame(array($mapping2, $mapping3), $this->manager->getResourceMappings(array('vendor/package1', 'vendor/package2'), ResourceMappingState::NOT_FOUND));
    }

    public function testGetConflictingMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/path1', 'resources'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/path1', 'resources'));
        $this->packageFile2->addResourceMapping($mapping3 = new ResourceMapping('/path2', 'resources'));
        $this->packageFile3->addResourceMapping($mapping4 = new ResourceMapping('/path2', 'resources'));
        $this->packageFile3->addResourceMapping($mapping5 = new ResourceMapping('/path3', 'resources'));

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
        ), $this->manager->getResourceMappings(null, ResourceMappingState::CONFLICT));

        $this->assertSame(array($mapping2), $this->manager->getResourceMappings('vendor/package1', ResourceMappingState::CONFLICT));
        $this->assertSame(array($mapping2, $mapping3), $this->manager->getResourceMappings(array('vendor/package1', 'vendor/package2'), ResourceMappingState::CONFLICT));
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

    public function testGetPathConflicts()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/path1', 'resources'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/path1', 'resources'));
        $this->packageFile2->addResourceMapping($mapping3 = new ResourceMapping('/path2', 'resources'));
        $this->packageFile3->addResourceMapping($mapping4 = new ResourceMapping('/path2', 'resources'));
        $this->packageFile3->addResourceMapping($mapping5 = new ResourceMapping('/path3', 'resources'));

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(2, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\RepositoryPathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/package1' => $mapping2,
            'vendor/root' => $mapping1,
        ), $conflicts[0]->getMappings());
        $this->assertInstanceOf('Puli\Manager\Api\Repository\RepositoryPathConflict', $conflicts[1]);
        $this->assertSame(array(
            'vendor/package2' => $mapping3,
            'vendor/package3' => $mapping4,
        ), $conflicts[1]->getMappings());
    }

    public function testGetPathConflictsIncludesNestedPathConflicts()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping($mapping1 = new ResourceMapping('/path', 'resources'));
        $this->packageFile2->addResourceMapping($mapping2 = new ResourceMapping('/path/config', 'override'));

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\RepositoryPathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/package1' => $mapping1,
            'vendor/package2' => $mapping2,
        ), $conflicts[0]->getMappings());
    }

    public function testGetPathConflictsMergesNestedConflicts()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addResourceMapping($mapping1 = new ResourceMapping('/path', 'override'));
        $this->packageFile1->addResourceMapping($mapping2 = new ResourceMapping('/path', 'resources'));

        // multiple conflicts: /path, /path/config, /path/config/config.yml
        // only /path is reported

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\RepositoryPathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/package1' => $mapping2,
            'vendor/root' => $mapping1,
        ), $conflicts[0]->getMappings());
    }

    public function testBuildRepository()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile1->addResourceMapping(new ResourceMapping('/path/css', 'assets/css'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresPackagesWithoutResources()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresNotFoundMappings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'foobar'));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresConflictingMappings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryAddsResourcesInSortedOrder()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/package/css', 'assets/css'));
        $this->packageFile1->addResourceMapping(new ResourceMapping('/package', 'resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverriding()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1/css', 'assets/css'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package1', 'override'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package1/css', 'css-override'));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));

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

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsChainedOverrides()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package1', 'override'));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));
        $this->packageFile3->addResourceMapping(new ResourceMapping('/package1', 'override2'));
        $this->packageFile3->setOverriddenPackages(array('vendor/package2'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir3.'/override2'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingMultiplePackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package2', 'resources'));
        $this->packageFile3->addResourceMapping(new ResourceMapping('/package1', 'override1'));
        $this->packageFile3->addResourceMapping(new ResourceMapping('/package2', 'override2'));
        $this->packageFile3->setOverriddenPackages(array('vendor/package1', 'vendor/package2'));

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

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresUnknownOverriddenPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/package', 'resources'));
        $this->packageFile1->setOverriddenPackages(array('foobar'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package', new DirectoryResource($this->packageDir1.'/resources'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingWithMultipleDirectories()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/package1', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/package1', array('override', 'css-override')));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/css-override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingOfNestedPaths()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path/new', 'override'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path/new', new DirectoryResource($this->packageDir2.'/override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverrideOrderInRootPackage()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->setOverrideOrder(array('vendor/package1', 'vendor/package2'));
        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path', 'override'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir2.'/override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresOverrideOrderInNonRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addResourceMapping(new ResourceMapping('/path', 'resources'));

        $this->packageFile2 = new RootPackageFile('vendor/package2');
        $this->packageFile2->addResourceMapping(new ResourceMapping('/path', 'override'));
        $this->packageFile2->setOverrideOrder(array('vendor/package1', 'vendor/package2'));

        // Update package file
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    /**
     * @expectedException \Puli\Manager\Api\Repository\RepositoryNotEmptyException
     */
    public function testBuildRepositoryFailsIfNotEmpty()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->once())
            ->method('hasChildren')
            ->with('/')
            ->willReturn(true);

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testClearRepository()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->once())
            ->method('clear');

        $this->manager->clearRepository();
    }

    private function initDefaultManager()
    {
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));
        $this->packages->add(new Package($this->packageFile1, $this->packageDir1));
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2));
        $this->packages->add(new Package($this->packageFile3, $this->packageDir3));

        $this->manager = new RepositoryManagerImpl($this->environment, $this->repo, $this->packages, $this->packageFileStorage);
    }
}
