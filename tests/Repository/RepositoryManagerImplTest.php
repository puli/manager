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
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Module\ModuleFileStorage;
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
    private $moduleDir1;

    /**
     * @var string
     */
    private $moduleDir2;

    /**
     * @var string
     */
    private $moduleDir3;

    /**
     * @var ModuleFile
     */
    private $moduleFile1;

    /**
     * @var ModuleFile
     */
    private $moduleFile2;

    /**
     * @var ModuleFile
     */
    private $moduleFile3;

    /**
     * @var ModuleList
     */
    private $modules;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ModuleFileStorage
     */
    private $moduleFileStorage;

    /**
     * @var RepositoryManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        $this->tempDir = TestUtil::makeTempDir('puli-manager', __CLASS__);

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->moduleDir1 = $this->tempDir.'/module1';
        $this->moduleDir2 = $this->tempDir.'/module2';
        $this->moduleDir3 = $this->tempDir.'/module3';

        $this->moduleFile1 = new ModuleFile('vendor/module1');
        $this->moduleFile2 = new ModuleFile('vendor/module2');
        $this->moduleFile3 = new ModuleFile('vendor/module3');

        $this->modules = new ModuleList();

        $this->moduleFileStorage = $this->getMockBuilder('Puli\Manager\Module\ModuleFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->initContext($this->tempDir.'/home', $this->tempDir.'/root');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testLoadIgnoresModulesWithoutModuleFile()
    {
        $this->modules->add(new RootModule($this->rootModuleFile, $this->rootDir));
        $this->modules->add(new Module(null, $this->moduleDir1));

        $this->manager = new RepositoryManagerImpl($this->context, $this->repo, $this->modules, $this->moduleFileStorage);

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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

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

        $this->rootModuleFile->addPathMapping(new PathMapping('/path', 'assets'));

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->never())
            ->method('add');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootPathMapping(new PathMapping('/path', 'resources'));
    }

    public function testAddRootPathMappingDoesNotFailIfPathAlreadyMappedAndOverride()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path', 'assets'));

        $mapping2 = new PathMapping('/path', 'resources');

        $this->repo->expects($this->at(0))
            ->method('remove');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($mapping2) {
                PHPUnit_Framework_Assert::assertSame(array('/path' => $mapping2), $rootModuleFile->getPathMappings());
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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources', 'assets'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path', array('resources', 'assets')));
    }

    public function testAddRootPathMappingWithReferenceToOtherModule()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@vendor/module1:resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path', '@vendor/module1:resources'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\NoSuchModuleException
     * @expectedExceptionMessage foobar
     */
    public function testAddRootPathMappingFailsIfReferencedModuleNotFound()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->never())
            ->method('add');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootPathMapping(new PathMapping('/path', '@foobar:resources'));
    }

    public function testAddRootPathMappingDoesNotFailIfReferencedModuleNotFoundAndIgnoreFileNotFound()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->never())
            ->method('add');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('@foobar:resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isNotFound());
            }));

        $this->manager->addRootPathMapping(new PathMapping('/path', '@foobar:resources'), RepositoryManager::IGNORE_FILE_NOT_FOUND);
    }

    public function testAddRootPathMappingOverridesConflictingModule()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/override'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());

                // Module was added to overridden modules
                PHPUnit_Framework_Assert::assertSame(array('vendor/module1', 'vendor/module2'), $rootModuleFile->getOverriddenModules());
            }));

        $this->rootModuleFile->setOverriddenModules(array('vendor/module1'));
        $this->moduleFile2->addPathMapping(new PathMapping('/path', 'resources'));

        $this->manager->addRootPathMapping(new PathMapping('/path', 'override'));

        // No conflict was added
        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testAddRootPathMappingOverridesMultipleConflictingModules()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('resources'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());

                // Only module2 was marked as overridden, because the
                // dependency between module1 and module2 is clearly defined
                PHPUnit_Framework_Assert::assertSame(array('vendor/module2'), $rootModuleFile->getOverriddenModules());
            }));

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/path', 'resources'));
        $this->moduleFile2->setOverriddenModules(array('vendor/module1'));

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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/module1'), $rootModuleFile->getOverriddenModules());
            }));

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));

        // Old conflict
        $this->moduleFile1->addPathMapping(new PathMapping('/old', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/old', 'resources'));

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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path', $mappings['/path']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/module1'), $rootModuleFile->getOverriddenModules());
            }));

        $this->moduleFile1->addPathMapping(new PathMapping('/path/config', 'resources'));

        // /override overrides /module1/resources/config
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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(1, $mappings);
                PHPUnit_Framework_Assert::assertSame('/path/config', $mappings['/path/config']->getRepositoryPath());
                PHPUnit_Framework_Assert::assertSame(array('override'), $mappings['/path/config']->getPathReferences());
                PHPUnit_Framework_Assert::assertTrue($mappings['/path/config']->isEnabled());
                PHPUnit_Framework_Assert::assertSame(array('vendor/module1'), $rootModuleFile->getOverriddenModules());
            }));

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));

        // /override overrides /module1/resources/config
        $this->manager->addRootPathMapping(new PathMapping('/path/config', 'override'));
    }

    public function testAddRootPathMappingRestoresOverriddenResourcesIfSavingFails()
    {
        $this->initDefaultManager();

        $mapping = new PathMapping('/path', 'resources');

        $this->moduleFile1->addPathMapping($existing = new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
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

        $this->moduleFile1->addPathMapping($existing = new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->rootModuleFile->addPathMapping($mapping = new PathMapping('/app', 'resources'));

        $this->manager->removeRootPathMapping('/app');

        $this->assertFalse($mapping->isLoaded());
    }

    public function testRemoveRootPathMappingIgnoresIfNotInRootModule()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->moduleFile1->addPathMapping($mapping = new PathMapping('/app', 'resources'));

        $this->manager->removeRootPathMapping('/app');

        $this->assertTrue($mapping->isEnabled());
    }

    public function testRemoveRootPathMappingDoesNothingIfUnknownPath()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('remove');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeRootPathMapping('/app');
    }

    public function testRemoveRootPathMappingRestoresOverriddenResource()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/module1');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->moduleFile1->addPathMapping(new PathMapping('/module1', 'resources'));
        $this->rootModuleFile->addPathMapping(new PathMapping('/module1', 'resources'));
        $this->rootModuleFile->setOverriddenModules(array('vendor/module1'));

        $this->manager->removeRootPathMapping('/module1');
    }

    public function testRemoveRootPathMappingRestoresOverriddenNestedResource1()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override overrides /module1/resources/config
        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->rootModuleFile->addPathMapping(new PathMapping('/path/config', 'override'));
        $this->rootModuleFile->setOverriddenModules(array('vendor/module1'));

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
            ->with('/path/config', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override/config overrides /module1/resources
        $this->moduleFile1->addPathMapping(new PathMapping('/path/config', 'resources'));
        $this->rootModuleFile->addPathMapping(new PathMapping('/path', 'override'));
        $this->rootModuleFile->setOverriddenModules(array('vendor/module1'));

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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        // /override overrides /module1/resources/config
        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/path', 'resources'));
        $this->rootModuleFile->addPathMapping(new PathMapping('/path/config', 'override'));
        $this->rootModuleFile->setOverriddenModules(array('vendor/module1'));

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
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $mappings = $rootModuleFile->getPathMappings();

                PHPUnit_Framework_Assert::assertCount(0, $mappings);
            }));

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->rootModuleFile->addPathMapping(new PathMapping('/path', 'resources'));

        $this->manager->removeRootPathMapping('/path');

        $this->assertCount(0, $this->manager->getPathConflicts());
    }

    public function testRemovesRootPathMappingRestoresResourcesIfSavingFails1()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->rootModuleFile->setOverriddenModules(array('vendor/module1'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        // Restore: only add root mapping
        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
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

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));
        $this->moduleFile1->setOverriddenModules(array('vendor/root'));

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        // Restore: remove and reapply
        $this->repo->expects($this->at(2))
            ->method('remove')
            ->with('/path');

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/path', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(4))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
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

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/path');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
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

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/app1', 'resources'));
        $this->rootModuleFile->addPathMapping($mapping2 = new PathMapping('/app2', 'resources'));
        $this->rootModuleFile->addPathMapping($mapping3 = new PathMapping('/other', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('remove')
            ->with('/app1');

        $this->repo->expects($this->at(1))
            ->method('remove')
            ->with('/app2');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($mapping3) {
                PHPUnit_Framework_Assert::assertSame(array('/other' => $mapping3), $rootModuleFile->getPathMappings());
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
            ->with('/path1', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('remove')
            ->with('/path2');

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/path2', new DirectoryResource($this->moduleDir1.'/resources'));

        // Restore: only add root mappings
        $this->repo->expects($this->at(4))
            ->method('add')
            ->with('/path2', new DirectoryResource($this->rootDir.'/resources'));

        $this->repo->expects($this->at(5))
            ->method('add')
            ->with('/path1', new DirectoryResource($this->rootDir.'/resources'));

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->willThrowException(new TestException('Cannot save'));

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootModuleFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->rootModuleFile->setOverriddenModules(array('vendor/module1'));
        $this->moduleFile1->addPathMapping($mapping3 = new PathMapping('/path1', 'resources'));
        $this->moduleFile1->addPathMapping($mapping4 = new PathMapping('/path2', 'resources'));

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

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasPathMappings());
            }));

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/app1', 'resources'));
        $this->rootModuleFile->addPathMapping($mapping2 = new PathMapping('/app2', 'resources'));

        $this->manager->clearRootPathMappings();

        $this->assertFalse($mapping1->isLoaded());
        $this->assertFalse($mapping2->isLoaded());
    }

    public function testGetRootPathMapping()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootModuleFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));

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
    public function testGetRootPathMappingFailsIfNotFoundInRootModule()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->manager->getRootPathMapping('/path', 'vendor/root');
    }

    public function testGetRootPathMappings()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootModuleFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->moduleFile1->addPathMapping($mapping3 = new PathMapping('/path3', 'resources'));

        $this->assertEquals(array(
            $mapping1,
            $mapping2,
        ), $this->manager->getRootPathMappings());
    }

    public function testFindRootPathMappings()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->rootModuleFile->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->moduleFile1->addPathMapping($mapping3 = new PathMapping('/path1', 'resources'));

        $expr1 = Expr::method('getRepositoryPath', Expr::startsWith('/path'));
        $expr2 = Expr::method('getRepositoryPath', Expr::same('/path2'));

        $this->assertSame(array($mapping1, $mapping2), $this->manager->findRootPathMappings($expr1));
        $this->assertSame(array($mapping2), $this->manager->findRootPathMappings($expr2));
    }

    public function testHasRootPathMapping()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->moduleFile1->addPathMapping(new PathMapping('/path2', 'resources'));

        $this->assertTrue($this->manager->hasRootPathMapping('/path1'));
        $this->assertFalse($this->manager->hasRootPathMapping('/path2'));
        $this->assertFalse($this->manager->hasRootPathMapping('/path3'));
    }

    public function testHasRootPathMappings()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->rootModuleFile->addPathMapping(new PathMapping('/path2', 'resources'));

        $expr1 = Expr::method('getRepositoryPath', Expr::same('/path1'));
        $expr2 = Expr::method('isConflict', Expr::same(true));

        $this->assertTrue($this->manager->hasRootPathMappings());
        $this->assertTrue($this->manager->hasRootPathMappings($expr1));
        $this->assertFalse($this->manager->hasRootPathMappings($expr2));
    }

    public function testGetPathMapping()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->assertSame($mapping1, $this->manager->getPathMapping('/path', 'vendor/root'));
        $this->assertSame($mapping2, $this->manager->getPathMapping('/path', 'vendor/module1'));
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
    public function testGetPathMappingFailsIfNotFoundInModule()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        $this->manager->getPathMapping('/path', 'vendor/root');
    }

    public function testGetPathMappings()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));
        $this->moduleFile2->addPathMapping($mapping3 = new PathMapping('/path', 'resources'));

        $this->assertEquals(array(
            $mapping1,
            $mapping2,
            $mapping3,
        ), $this->manager->getPathMappings());
    }

    public function testFindPathMappings()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path2', 'resources'));
        $this->moduleFile2->addPathMapping($mapping3 = new PathMapping('/path1', 'resources'));

        $expr1 = Expr::method('getRepositoryPath', Expr::same('/path1'));
        $expr2 = $expr1->andMethod('getContainingModule', Expr::method('getName', Expr::same('vendor/root')));
        $expr3 = $expr1->andMethod('getContainingModule', Expr::method('getName', Expr::same('vendor/module1')));

        $this->assertSame(array($mapping1, $mapping3), $this->manager->findPathMappings($expr1));
        $this->assertSame(array($mapping1), $this->manager->findPathMappings($expr2));
        $this->assertSame(array(), $this->manager->findPathMappings($expr3));
    }

    public function testHasPathMapping()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->moduleFile1->addPathMapping(new PathMapping('/path2', 'resources'));

        $this->assertTrue($this->manager->hasPathMapping('/path1', 'vendor/root'));
        $this->assertTrue($this->manager->hasPathMapping('/path2', 'vendor/module1'));
        $this->assertFalse($this->manager->hasPathMapping('/path1', 'vendor/module1'));
        $this->assertFalse($this->manager->hasPathMapping('/path2', 'vendor/root'));
        $this->assertFalse($this->manager->hasPathMapping('/path3', 'vendor/root'));
    }

    public function testHasPathMappings()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping(new PathMapping('/path1', 'resources'));
        $this->moduleFile1->addPathMapping(new PathMapping('/path2', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/path2', 'resources'));

        $expr1 = Expr::method('getContainingModule', Expr::method('getName', Expr::same('vendor/module1')));
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

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path1', 'resources'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path1', 'resources'));
        $this->moduleFile2->addPathMapping($mapping3 = new PathMapping('/path2', 'resources'));
        $this->moduleFile3->addPathMapping($mapping4 = new PathMapping('/path2', 'resources'));
        $this->moduleFile3->addPathMapping($mapping5 = new PathMapping('/path3', 'resources'));

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(2, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/module1' => $mapping2,
            'vendor/root' => $mapping1,
        ), $conflicts[0]->getMappings());
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[1]);
        $this->assertSame(array(
            'vendor/module2' => $mapping3,
            'vendor/module3' => $mapping4,
        ), $conflicts[1]->getMappings());
    }

    public function testGetPathConflictsIncludesNestedPathConflicts()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping($mapping1 = new PathMapping('/path', 'resources'));
        $this->moduleFile2->addPathMapping($mapping2 = new PathMapping('/path/config', 'override'));

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/module1' => $mapping1,
            'vendor/module2' => $mapping2,
        ), $conflicts[0]->getMappings());
    }

    public function testGetPathConflictsMergesNestedConflicts()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addPathMapping($mapping1 = new PathMapping('/path', 'override'));
        $this->moduleFile1->addPathMapping($mapping2 = new PathMapping('/path', 'resources'));

        // multiple conflicts: /path, /path/config, /path/config/config.yml
        // only /path is reported

        $conflicts = $this->manager->getPathConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Api\Repository\PathConflict', $conflicts[0]);
        $this->assertSame(array(
            'vendor/module1' => $mapping2,
            'vendor/root' => $mapping1,
        ), $conflicts[0]->getMappings());
    }

    public function testBuildRepository()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->moduleFile1->addPathMapping(new PathMapping('/path/css', 'assets/css'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path/css', new DirectoryResource($this->moduleDir1.'/assets/css'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresModulesWithoutResources()
    {
        $this->initDefaultManager();

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresNotFoundMappings()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'foobar'));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresConflictingMappings()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/path', 'resources'));

        $this->repo->expects($this->never())
            ->method('add');

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryAddsResourcesInSortedOrder()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/module/css', 'assets/css'));
        $this->moduleFile1->addPathMapping(new PathMapping('/module', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/module', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/module/css', new DirectoryResource($this->moduleDir1.'/assets/css'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverriding()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/module1', 'resources'));
        $this->moduleFile1->addPathMapping(new PathMapping('/module1/css', 'assets/css'));
        $this->moduleFile2->addPathMapping(new PathMapping('/module1', 'override'));
        $this->moduleFile2->addPathMapping(new PathMapping('/module1/css', 'css-override'));
        $this->moduleFile2->setOverriddenModules(array('vendor/module1'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/module1/css', new DirectoryResource($this->moduleDir1.'/assets/css'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir2.'/override'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/module1/css', new DirectoryResource($this->moduleDir2.'/css-override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsChainedOverrides()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/module1', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/module1', 'override'));
        $this->moduleFile2->setOverriddenModules(array('vendor/module1'));
        $this->moduleFile3->addPathMapping(new PathMapping('/module1', 'override2'));
        $this->moduleFile3->setOverriddenModules(array('vendor/module2'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir2.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir3.'/override2'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingMultipleModules()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/module1', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/module2', 'resources'));
        $this->moduleFile3->addPathMapping(new PathMapping('/module1', 'override1'));
        $this->moduleFile3->addPathMapping(new PathMapping('/module2', 'override2'));
        $this->moduleFile3->setOverriddenModules(array('vendor/module1', 'vendor/module2'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/module2', new DirectoryResource($this->moduleDir2.'/resources'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir3.'/override1'));

        $this->repo->expects($this->at(3))
            ->method('add')
            ->with('/module2', new DirectoryResource($this->moduleDir3.'/override2'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresUnknownOverriddenModule()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/module', 'resources'));
        $this->moduleFile1->setOverriddenModules(array('foobar'));

        $this->repo->expects($this->once())
            ->method('add')
            ->with('/module', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingWithMultipleDirectories()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/module1', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/module1', array('override', 'css-override')));
        $this->moduleFile2->setOverriddenModules(array('vendor/module1'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir2.'/override'));

        $this->repo->expects($this->at(2))
            ->method('add')
            ->with('/module1', new DirectoryResource($this->moduleDir2.'/css-override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverridingOfNestedPaths()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/path/new', 'override'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path/new', new DirectoryResource($this->moduleDir2.'/override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositorySupportsOverrideOrderInRootModule()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->setOverrideOrder(array('vendor/module1', 'vendor/module2'));
        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));
        $this->moduleFile2->addPathMapping(new PathMapping('/path', 'override'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir2.'/override'));

        $this->manager->buildRepository();
    }

    public function testBuildRepositoryIgnoresOverrideOrderInNonRootModule()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));

        $this->moduleFile2 = new RootModuleFile('vendor/module2');
        $this->moduleFile2->addPathMapping(new PathMapping('/path', 'override'));
        $this->moduleFile2->setOverrideOrder(array('vendor/module1', 'vendor/module2'));

        // Update module file
        $this->modules->add(new Module($this->moduleFile2, $this->moduleDir2));

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

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));

        $this->repo->expects($this->at(0))
            ->method('add')
            ->with('/pre', $this->identicalTo($testResource1));

        $this->repo->expects($this->at(1))
            ->method('add')
            ->with('/path', new DirectoryResource($this->moduleDir1.'/resources'));

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

        $this->moduleFile1->addPathMapping(new PathMapping('/path', 'resources'));

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
        $this->modules->add(new RootModule($this->rootModuleFile, $this->rootDir));
        $this->modules->add(new Module($this->moduleFile1, $this->moduleDir1));
        $this->modules->add(new Module($this->moduleFile2, $this->moduleDir2));
        $this->modules->add(new Module($this->moduleFile3, $this->moduleDir3));

        $this->manager = new RepositoryManagerImpl($this->context, $this->repo, $this->modules, $this->moduleFileStorage);
    }
}
