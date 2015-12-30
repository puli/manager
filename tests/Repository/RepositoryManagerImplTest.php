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
use Puli\Manager\Api\Event\BuildRepositoryEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Repository\RepositoryManagerImpl;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Manager\Tests\TestException;
use Puli\Repository\Resource\DirectoryResource;
use Puli\Repository\Resource\FileResource;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Expression\Expr;
use Webmozart\Glob\Test\TestUtil;

/**
 * @since  1.0
 *
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
        $this->tempDir = TestUtil::makeTempDir('puli-manager', __CLASS__);

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

        $this->initContext($this->tempDir.'/home', $this->tempDir.'/root');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testLoadIgnoresPackagesWithoutPackageFile()
    {
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));
        $this->packages->add(new Package(null, $this->packageDir1));

        $this->manager = new RepositoryManagerImpl($this->context, $this->repo, $this->packages, $this->packageFileStorage);

        $this->assertEmpty($this->manager->getPathMappings());
    }

    public function testAddRootPathMappingWithDirectoryPath()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path', 'resources'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Repository\DuplicatePathMappingException
     */
    public function testAddRootPathMappingFailsIfPathAlreadyMapped()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping(new PathMapping('/path', 'assets'));

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->never())
            ->method('add');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootPathMapping(new PathMapping('/path', 'resources'));
    }

    public function testAddRootPathMappingDoesNotFailIfPathAlreadyMappedAndOverride()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path', 'assets'));

        $mapping2 = new PathMapping('/path', 'resources');

        $this->repo->expects($this->at(0))
            ->method('remove');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping2) {
                PHPUnit_Framework_Assert::assertSame(array('/path' => $mapping2), $rootPackageFile->getPathMappings());
            }));

        $this->manager->addRootPathMapping($mapping2, RepositoryManager::OVERRIDE);
    }

    public function testAddRootPathMappingWithFilePath()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path/file', $mappings['/path/file']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources/file'), $mappings['/path/file']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path/file']->isEnabled());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path/file', 'resources/file'));
    }

    public function testAddRootPathMappingWithMultiplePaths()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources', 'assets'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path', array('resources', 'assets')));
    }

    public function testAddRootPathMappingWithReferenceToOtherPackage()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@vendor/package1:resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path', '@vendor/package1:resources'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Package\NoSuchPackageException
     * @expectedExceptionMessage foobar
     */
    public function testAddRootPathMappingFailsIfReferencedPackageNotFound()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->never())
            ->method('add');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootPathMapping(new PathMapping('/path', '@foobar:resources'));
    }

    public function testAddRootPathMappingDoesNotFailIfReferencedPackageNotFoundAndIgnoreFileNotFound()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@foobar:resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isNotFound());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path', '@foobar:resources'), RepositoryManager::IGNORE_FILE_NOT_FOUND);
    }

    public function testAddRootPathMappingOverridesConflictingPackage()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());

                // Package was added to overridden packages
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1', 'vendor/package2'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));
        $this->packageFile2->addPathMapping(new PathMapping('/path', 'resources'));

        $this->manager->addRootPathMapping(new PathMapping('/path', 'override'));

        // No conflict was added
        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testAddRootPathMappingOverridesMultipleConflictingPackages()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());

                // Only package2 was marked as overridden, because the
                // dependency between package1 and package2 is clearly defined
                PHPUnit_Framework_Assert::assertSame(array('vendor/package2'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/path', 'resources'));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));

        $this->manager->addRootPathMapping(new PathMapping('/path', 'resources'));
    }

    public function testAddRootPathMappingWithConflictDoesNotChangeExistingConflicts()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));

        // Old conflict
        $this->packageFile1->addPathMapping(new PathMapping('/old', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/old', 'resources'));

        $this->manager->addRootPathMapping(new PathMapping('/path', 'override'));

        // Old conflict still exists
        $conflicts = $this->manager->getPathConflicts();
        $this->assertCount(1, $conflicts);
        $this->assertSame('/old', $conflicts[0]->getRepositoryPath());
    }

    public function testAddRootPathMappingOverridesNestedPath1()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addPathMapping(new PathMapping('/path/config', 'resources'));

        // /override overrides /package1/resources/config
        $this->manager->addRootPathMapping(new PathMapping('/path', 'override'));
    }

    public function testAddRootPathMappingOverridesNestedPath2()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path/config', $mappings['/path/config']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path/config']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path/config']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/package1'), $rootPackageFile->getOverriddenPackages());
            }));

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));

        // /override overrides /package1/resources/config
        $this->manager->addRootPathMapping(new PathMapping('/path/config', 'override'));
    }

    public function testAddRootPathMappingRestoresOverriddenResourcesIfSavingFails()
    {
        $this->initDefaultManager();

        $mapping = new PathMapping('/path', 'resources');

        $this->packageFile1->addPathMapping($existing = new PathMapping('/path', 'resources'));

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
            $this->manager->addRootPathMapping($mapping);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($existing->isEnabled());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testAddRootPathMappingRemovesNewConflictsIfSavingFails()
    {
        $this->initDefaultManager();

        $mapping = new PathMapping('/path', 'resources');

        $this->packageFile1->addPathMapping($existing = new PathMapping('/path', 'resources'));

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
            $this->manager->addRootPathMapping($mapping);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($existing->isEnabled());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testRemoveRootPathMapping()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->once())
            ->method('remove')
            ->with('/app');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->rootPackageFile->addPathMapping($mapping = new PathMapping('/app', 'resources'));

        $this->manager->removeRootPathMapping('/app');

        $this->assertFalse($mapping->isLoaded());
    }

    public function testRemoveRootPathMappingIgnoresIfNotInRootPackage()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->packageFile1->addPathMapping($mapping = new PathMapping('/app', 'resources'));

        $this->manager->removeRootPathMapping('/app');

        $this->assertTrue($mapping->isEnabled());
    }

    public function testRemoveRootPathMappingDoesNothingIfUnknownPath()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeRootPathMapping('/app');
    }

    public function testRemoveRootPathMappingRestoresOverriddenResource()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->packageFile1->addPathMapping(new PathMapping('/package1', 'resources'));
        $this->rootPackageFile->addPathMapping(new PathMapping('/package1', 'resources'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeRootPathMapping('/package1');
    }

    public function testRemoveRootPathMappingRestoresOverriddenNestedResource1()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override overrides /package1/resources/config
        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->rootPackageFile->addPathMapping(new PathMapping('/path/config', 'override'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeRootPathMapping('/path/config');
    }

    public function testRemoveRootPathMappingRestoresOverriddenNestedResource2()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override/config overrides /package1/resources
        $this->packageFile1->addPathMapping(new PathMapping('/path/config', 'resources'));
        $this->rootPackageFile->addPathMapping(new PathMapping('/path', 'override'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeRootPathMapping('/path');
    }

    public function testRemoveRootPathMappingDoesNotRestoreOverriddenNestedResourceIfNotEnabled()
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override overrides /package1/resources/config
        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/path', 'resources'));
        $this->rootPackageFile->addPathMapping(new PathMapping('/path/config', 'override'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));

        $this->manager->removeRootPathMapping('/path/config');
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
                $mappings = $rootPackageFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->rootPackageFile->addPathMapping(new PathMapping('/path', 'resources'));

        $this->manager->removeRootPathMapping('/path');

        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testRemovesRootPathMappingRestoresResourcesIfSavingFails1()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        // Restore: only add root mapping
        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException('Cannot save'));

        try {
            $this->manager->removeRootPathMapping('/path');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($mapping1->isEnabled());
        $this->assertTrue($mapping2->isEnabled());
        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testRemovesRootPathMappingRestoresResourcesIfSavingFails2()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));
        $this->packageFile1->setOverriddenPackages(array('vendor/root'));

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        // Restore: remove and reapply
        $this->repo->expects($this->at(2))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(4))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException('Cannot save'));

        try {
            $this->manager->removeRootPathMapping('/path');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($mapping1->isEnabled());
        $this->assertTrue($mapping2->isEnabled());
        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testRemovesRootPathMappingRestoresConflictsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/path');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException('Cannot save'));

        try {
            $this->manager->removeRootPathMapping('/path');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($mapping1->isConflicting());
        $this->assertTrue($mapping2->isConflicting());
        $this->assertCount(1, $this->manager->getPathConflicts());
    }

    public function testRemoveRootPathMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/app1', 'resources'));
        $this->rootPackageFile->addPathMapping($mapping2 = new PathMapping('/app2', 'resources'));
        $this->rootPackageFile->addPathMapping($mapping3 = new PathMapping('/other', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/app1');

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/app2');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping3) {
                PHPUnit_Framework_Assert::assertSame(array('/other' => $mapping3), $rootPackageFile->getPathMappings());
            }));

        $this->manager->removeRootPathMappings(Expr::method('getRepositoryPath', Expr::startsWith('/app')));

        $this->assertFalse($mapping1->isLoaded());
        $this->assertFalse($mapping2->isLoaded());
        $this->assertTrue($mapping3->isLoaded());
    }

    public function testRemoveRootPathMappingRestoresResourcesIfSavingFails1()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path1');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('remove')
            ->with('/path2');

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/path2', new DirectoryResource($this->packageDir1.'/resources'));

        // Restore: only add root mappings
        $this->repo->expects($this->at(4))
            ->method('add')
            ->with('/path2', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(5))
            ->method('add')
            ->with('/path1', new DirectoryResource($this->rootDir.'/resources'));

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->willThrowException(new TestException('Cannot save'));

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootPackageFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->rootPackageFile->setOverriddenPackages(array('vendor/package1'));
        $this->packageFile1->addPathMapping($mapping3 = new PathMapping('/path1', 'resources'));
        $this->packageFile1->addPathMapping($mapping4 = new PathMapping('/path2', 'resources'));

        try {
            $this->manager->removeRootPathMappings(Expr::method('getRepositoryPath', Expr::startsWith('/path')));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertTrue($mapping1->isEnabled());
        $this->assertTrue($mapping2->isEnabled());
        $this->assertTrue($mapping3->isEnabled());
        $this->assertTrue($mapping4->isEnabled());
        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testClearRootPathMappings()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/app1');

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/app2');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasPathMappings());
            }));

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/app1', 'resources'));
        $this->rootPackageFile->addPathMapping($mapping2 = new PathMapping('/app2', 'resources'));

        $this->manager->clearRootPathMappings();

        $this->assertFalse($mapping1->isLoaded());
        $this->assertFalse($mapping2->isLoaded());
    }

    public function testGetRootPathMapping()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootPackageFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));

        $this->assertSame($mapping1, $this->manager->getRootPathMapping('/path1'));
        $this->assertSame($mapping2, $this->manager->getRootPathMapping('/path2'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Repository\NoSuchPathMappingException
     */
    public function testGetRootPathMappingFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getRootPathMapping('/path', 'vendor/root');
    }

    /**
     * @expectedException \Puli\Manager\Api\Repository\NoSuchPathMappingException
     */
    public function testGetRootPathMappingFailsIfNotFoundInRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->manager->getRootPathMapping('/path', 'vendor/root');
    }

    public function testGetRootPathMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootPackageFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->packageFile1->addPathMapping($mapping3 = new PathMapping('/path3', 'resources'));

        $this->assertEquals(array(
            $mapping1,
            $mapping2,
        ), $this->manager->getRootPathMappings());
    }

    public function testFindRootPathMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootPackageFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->packageFile1->addPathMapping($mapping3 = new PathMapping('/path1', 'resources'));

        $expr1 = Expr::method('getRepositoryPath', Expr::startsWith('/path'));
        $expr2 = Expr::method('getRepositoryPath', Expr::same('/path2'));

        $this->assertSame(array($mapping1, $mapping2), $this->manager->findRootPathMappings($expr1));
        $this->assertSame(array($mapping2), $this->manager->findRootPathMappings($expr2));
    }

    public function testHasRootPathMapping()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->packageFile1->addPathMapping(new PathMapping('/path2', 'resources'));

        $this->assertTrue($this->manager->hasRootPathMapping('/path1'));
        $this->assertFalse($this->manager->hasRootPathMapping('/path2'));
        $this->assertFalse($this->manager->hasRootPathMapping('/path3'));
    }

    public function testHasRootPathMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->rootPackageFile->addPathMapping(new PathMapping('/path2', 'resources'));

        $expr1 = Expr::method('getRepositoryPath', Expr::same('/path1'));
        $expr2 = Expr::method('isConflict', Expr::same(true));

        $this->assertTrue($this->manager->hasRootPathMappings());
        $this->assertTrue($this->manager->hasRootPathMappings($expr1));
        $this->assertFalse($this->manager->hasRootPathMappings($expr2));
    }

    public function testGetPathMapping()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->assertSame($mapping1, $this->manager->getPathMapping('/path', 'vendor/root'));
        $this->assertSame($mapping2, $this->manager->getPathMapping('/path', 'vendor/package1'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Repository\NoSuchPathMappingException
     */
    public function testGetPathMappingFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getPathMapping('/path', 'vendor/root');
    }

    /**
     * @expectedException \Puli\Manager\Api\Repository\NoSuchPathMappingException
     */
    public function testGetPathMappingFailsIfNotFoundInPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->manager->getPathMapping('/path', 'vendor/root');
    }

    public function testGetPathMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));
        $this->packageFile2->addPathMapping($mapping3 = new PathMapping('/path', 'resources'));

        $this->assertEquals(array(
            $mapping1,
            $mapping2,
            $mapping3,
        ), $this->manager->getPathMappings());
    }

    public function testFindPathMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->packageFile2->addPathMapping($mapping3 = new PathMapping('/path1', 'resources'));

        $expr1 = Expr::method('getRepositoryPath', Expr::same('/path1'));
        $expr2 = $expr1->andMethod('getContainingPackage', Expr::method('getName', Expr::same('vendor/root')));
        $expr3 = $expr1->andMethod('getContainingPackage', Expr::method('getName', Expr::same('vendor/package1')));

        $this->assertSame(array($mapping1, $mapping3), $this->manager->findPathMappings($expr1));
        $this->assertSame(array($mapping1), $this->manager->findPathMappings($expr2));
        $this->assertSame(array(), $this->manager->findPathMappings($expr3));
    }

    public function testHasPathMapping()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->packageFile1->addPathMapping(new PathMapping('/path2', 'resources'));

        $this->assertTrue($this->manager->hasPathMapping('/path1', 'vendor/root'));
        $this->assertTrue($this->manager->hasPathMapping('/path2', 'vendor/package1'));
        $this->assertFalse($this->manager->hasPathMapping('/path1', 'vendor/package1'));
        $this->assertFalse($this->manager->hasPathMapping('/path2', 'vendor/root'));
        $this->assertFalse($this->manager->hasPathMapping('/path3', 'vendor/root'));
    }

    public function testHasPathMappings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->packageFile1->addPathMapping(new PathMapping('/path2', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/path2', 'resources'));

        $expr1 = Expr::method('getContainingPackage', Expr::method('getName', Expr::same('vendor/package1')));
        $expr2 = Expr::method('isEnabled', Expr::same(true));
        $expr3 = $expr1->andX($expr2);

        $this->assertTrue($this->manager->hasPathMappings());
        $this->assertTrue($this->manager->hasPathMappings($expr1));
        $this->assertTrue($this->manager->hasPathMappings($expr2));
        $this->assertFalse($this->manager->hasPathMappings($expr3));
    }

    public function testHasNoPathMappings()
    {
        $this->initDefaultManager();

        $this->assertFalse($this->manager->hasPathMappings());
    }

    public function testGetPathConflicts()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path1', 'resources'));
        $this->packageFile2->addPathMapping($mapping3 = new PathMapping('/path2', 'resources'));
        $this->packageFile3->addPathMapping($mapping4 = new PathMapping('/path2', 'resources'));
        $this->packageFile3->addPathMapping($mapping5 = new PathMapping('/path3', 'resources'));

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(2, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/package1' => $mapping2,
            'vendor/root' => $mapping1,
        ), $conflicts[0]->getMappings());
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[1]);
        $this->assertSame(array(
            'vendor/package2' => $mapping3,
            'vendor/package3' => $mapping4,
        ), $conflicts[1]->getMappings());
    }

    public function testGetPathConflictsIncludesNestedPathConflicts()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->packageFile2->addPathMapping($mapping2 = new PathMapping('/path/config', 'override'));

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/package1' => $mapping1,
            'vendor/package2' => $mapping2,
        ), $conflicts[0]->getMappings());
    }

    public function testGetPathConflictsMergesNestedConflicts()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addPathMapping($mapping1 = new PathMapping('/path', 'override'));
        $this->packageFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        // multiple conflicts: /path, /path/config, /path/config/config.yml
        // only /path is reported

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/package1' => $mapping2,
            'vendor/root' => $mapping1,
        ), $conflicts[0]->getMappings());
    }

    public function testBuildRepository()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->packageFile1->addPathMapping(new PathMapping('/path/css', 'assets/css'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
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

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'foobar'));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresConflictingMappings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/path', 'resources'));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryAddsResourcesInSortedOrder()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/package/css', 'assets/css'));
        $this->packageFile1->addPathMapping(new PathMapping('/package', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverriding()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/package1', 'resources'));
        $this->packageFile1->addPathMapping(new PathMapping('/package1/css', 'assets/css'));
        $this->packageFile2->addPathMapping(new PathMapping('/package1', 'override'));
        $this->packageFile2->addPathMapping(new PathMapping('/package1/css', 'css-override'));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1/css', new DirectoryResource($this->packageDir1.'/assets/css'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package1/css', new DirectoryResource($this->packageDir2.'/css-override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsChainedOverrides()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/package1', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/package1', 'override'));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));
        $this->packageFile3->addPathMapping(new PathMapping('/package1', 'override2'));
        $this->packageFile3->setOverriddenPackages(array('vendor/package2'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir3.'/override2'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingMultiplePackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/package1', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/package2', 'resources'));
        $this->packageFile3->addPathMapping(new PathMapping('/package1', 'override1'));
        $this->packageFile3->addPathMapping(new PathMapping('/package2', 'override2'));
        $this->packageFile3->setOverriddenPackages(array('vendor/package1', 'vendor/package2'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package2', new DirectoryResource($this->packageDir2.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir3.'/override1'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/package2', new DirectoryResource($this->packageDir3.'/override2'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresUnknownOverriddenPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/package', 'resources'));
        $this->packageFile1->setOverriddenPackages(array('foobar'));

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/package', new DirectoryResource($this->packageDir1.'/resources'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingWithMultipleDirectories()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/package1', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/package1', array('override', 'css-override')));
        $this->packageFile2->setOverriddenPackages(array('vendor/package1'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/package1', new DirectoryResource($this->packageDir2.'/css-override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingOfNestedPaths()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/path/new', 'override'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path/new', new DirectoryResource($this->packageDir2.'/override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverrideOrderInRootPackage()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->setOverrideOrder(array('vendor/package1', 'vendor/package2'));
        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->packageFile2->addPathMapping(new PathMapping('/path', 'override'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir2.'/override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresOverrideOrderInNonRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));

        $this->packageFile2 = new RootPackageFile('vendor/package2');
        $this->packageFile2->addPathMapping(new PathMapping('/path', 'override'));
        $this->packageFile2->setOverrideOrder(array('vendor/package1', 'vendor/package2'));

        // Update package file
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryDispatchesEvent()
    {
        $testResource1 = new FileResource(__FILE__);
        $testResource2 = new FileResource(__FILE__);

        $this->initContext($this->homeDir, $this->rootDir, false);
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/pre', $this->identicalTo($testResource1));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->packageDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/post', $this->identicalTo($testResource2));

        $this->dispatcher->addListener(
            PuliEvents::PRE_BUILD_REPOSITORY,
            function (BuildRepositoryEvent $event) use ($testResource1) {
                $event->getRepositoryManager()->getRepository()->add('/pre', $testResource1);
            }
        );

        $this->dispatcher->addListener(
            PuliEvents::POST_BUILD_REPOSITORY,
            function (BuildRepositoryEvent $event) use ($testResource2) {
                $event->getRepositoryManager()->getRepository()->add('/post', $testResource2);
            }
        );

        $this->manager->buildRepository();
    }

    public function testPreBuildRepositoryEventSupportsSkipping()
    {
        $testResource = new FileResource(__FILE__);

        $this->initContext($this->homeDir, $this->rootDir, false);
        $this->initDefaultManager();

        $this->packageFile1->addPathMapping(new PathMapping('/path', 'resources'));

        $this->repo->expects($this->never())
            ->method('add');

        $this->dispatcher->addListener(
            PuliEvents::PRE_BUILD_REPOSITORY,
            function (BuildRepositoryEvent $event) {
                $event->skipBuild();
            }
        );

        $this->dispatcher->addListener(
            PuliEvents::POST_BUILD_REPOSITORY,
            function (BuildRepositoryEvent $event) use ($testResource) {
                // The post event is not executed if the build is skipped
                $event->getRepositoryManager()->getRepository()->add('/post', $testResource);
            }
        );

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

        $this->manager = new RepositoryManagerImpl($this->context, $this->repo, $this->packages, $this->packageFileStorage);
    }
}
