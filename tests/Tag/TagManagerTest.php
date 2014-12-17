<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Tag;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Tag\TagDefinition;
use Puli\RepositoryManager\Tag\TagManager;
use Puli\RepositoryManager\Tag\TagMapping;
use Puli\RepositoryManager\Tests\ManagerTestCase;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagManagerTest extends ManagerTestCase
{
    /**
     * @var PackageCollection
     */
    private $packages;

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
     * @var RootPackage
     */
    private $rootPackage;

    /**
     * @var Package
     */
    private $package1;

    /**
     * @var Package
     */
    private $package2;

    /**
     * @var Package
     */
    private $package3;

    /**
     * @var InstallInfo
     */
    private $installInfo1;

    /**
     * @var InstallInfo
     */
    private $installInfo2;

    /**
     * @var InstallInfo
     */
    private $installInfo3;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var TagManager
     */
    private $manager;

    protected function setUp()
    {
        $this->initEnvironment(__DIR__.'/Fixtures/home', __DIR__.'/Fixtures/root');

        $this->packageFile1 = new PackageFile(null, __DIR__.'/Fixtures/package1/puli.json');
        $this->packageFile2 = new PackageFile(null, __DIR__.'/Fixtures/package2/puli.json');
        $this->packageFile3 = new PackageFile(null, __DIR__.'/Fixtures/package3/puli.json');

        $this->installInfo1 = new InstallInfo('package1', '../package1');
        $this->installInfo2 = new InstallInfo('package2', '../package2');
        $this->installInfo3 = new InstallInfo('package3', '../package3');

        $this->rootPackageFile->addInstallInfo($this->installInfo1);
        $this->rootPackageFile->addInstallInfo($this->installInfo2);
        $this->rootPackageFile->addInstallInfo($this->installInfo3);

        $this->rootPackage = new RootPackage($this->rootPackageFile, $this->rootDir);
        $this->package1 = new Package($this->packageFile1, __DIR__.'/Fixtures/package1', $this->installInfo1);
        $this->package2 = new Package($this->packageFile2, __DIR__.'/Fixtures/package2', $this->installInfo2);
        $this->package3 = new Package($this->packageFile3, __DIR__.'/Fixtures/package3', $this->installInfo3);

        $this->packages = new PackageCollection(array(
            $this->rootPackage,
            $this->package1,
            $this->package2,
            $this->package3,
        ));

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFile\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetRootTagMappings()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));

        $this->rootPackageFile->addTagMapping($rootMapping1 = new TagMapping('/path1', 'tag1'));
        $this->rootPackageFile->addTagMapping($rootMapping2 = new TagMapping('/path2', 'tag2'));

        $this->rootPackageFile->addTagMapping($undefinedRootMapping = new TagMapping('/path3', 'foo'));
        $this->packageFile1->addTagMapping($newPackageMapping = new TagMapping('/path4', 'tag1'));
        $this->installInfo1->addEnabledTagMapping($enabledPackageMapping = new TagMapping('/path5', 'tag1'));
        $this->installInfo2->addDisabledTagMapping($disabledPackageMapping = new TagMapping('/path6', 'tag2'));

        $this->initManager();

        $this->assertSame(array(
            $rootMapping1,
            $rootMapping2,
        ), $this->manager->getRootTagMappings());

        $this->assertSame(array($rootMapping1), $this->manager->getRootTagMappings('tag1'));
        $this->assertSame(array($rootMapping2), $this->manager->getRootTagMappings('tag2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\UndefinedTagException
     * @expectedExceptionMessage foobar
     */
    public function testGetRootTagMappingsFailsIfTagNotDefined()
    {
        $this->initManager();

        $this->manager->getRootTagMappings('foobar');
    }

    public function testFindRootTagMappings()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));

        $this->rootPackageFile->addTagMapping($rootMapping1 = new TagMapping('/path1', 'tag1'));
        $this->rootPackageFile->addTagMapping($rootMapping2 = new TagMapping('/path2', 'tag2'));
        $this->rootPackageFile->addTagMapping($rootMapping3 = new TagMapping('/path1', 'tag2'));

        $this->rootPackageFile->addTagMapping($undefinedRootMapping = new TagMapping('/path3', 'foo'));
        $this->packageFile1->addTagMapping($newPackageMapping = new TagMapping('/path4', 'tag1'));
        $this->installInfo1->addEnabledTagMapping($enabledPackageMapping = new TagMapping('/path5', 'tag1'));
        $this->installInfo2->addDisabledTagMapping($disabledPackageMapping = new TagMapping('/path6', 'tag2'));

        $this->initManager();

        $this->assertSame(array(
            $rootMapping1,
            $rootMapping2,
            $rootMapping3,
        ), $this->manager->findRootTagMappings());

        $this->assertSame(array($rootMapping1, $rootMapping3), $this->manager->findRootTagMappings('*path1'));
        $this->assertSame(array($rootMapping1, $rootMapping3), $this->manager->findRootTagMappings('*path1*'));
        $this->assertSame(array($rootMapping2, $rootMapping3), $this->manager->findRootTagMappings(null, '*g2'));
        $this->assertSame(array($rootMapping2, $rootMapping3), $this->manager->findRootTagMappings(null, '*g2*'));
        $this->assertSame(array($rootMapping1), $this->manager->findRootTagMappings('*path1', '*g1'));
    }

    public function testAddRootTagMapping()
    {
        $mapping1 = new TagMapping('/path1', 'tag1');
        $mapping2 = new TagMapping('/path2', 'tag2');

        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));
        $this->rootPackageFile->addTagMapping($mapping1);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping1, $mapping2) {
                $tagMappings = $rootPackageFile->getTagMappings();

                PHPUnit_Framework_Assert::assertCount(2, $tagMappings);
                PHPUnit_Framework_Assert::assertSame($mapping1, $tagMappings[0]);
                PHPUnit_Framework_Assert::assertSame($mapping2, $tagMappings[1]);
            }));

        $this->manager->addRootTagMapping($mapping2);
    }

    public function testAddRootTagMappingIgnoresDuplicates()
    {
        $mapping = new TagMapping('/path1', 'tag1');

        $this->packageFile1->addTagDefinition(new TagDefinition('tag1'));

        // compare by value -> clone
        $this->rootPackageFile->addTagMapping(clone $mapping);

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootTagMapping($mapping);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\UndefinedTagException
     * @expectedExceptionMessage foobar
     */
    public function testAddRootTagMappingFailsIfTagNotDefined()
    {
        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootTagMapping(new TagMapping('/path1', 'foobar'));
    }

    public function testRemoveRootTagMapping()
    {
        $mapping1 = new TagMapping('/path1', 'tag1');
        $mapping2 = new TagMapping('/path2', 'tag2');

        $this->rootPackageFile->addTagMapping($mapping1);
        $this->rootPackageFile->addTagMapping($mapping2);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping2) {
                $tagMappings = $rootPackageFile->getTagMappings();

                PHPUnit_Framework_Assert::assertCount(1, $tagMappings);
                PHPUnit_Framework_Assert::assertSame($mapping2, $tagMappings[0]);
            }));

        $this->manager->removeRootTagMapping($mapping1);
    }

    public function testRemoveRootTagMappingIgnoresNonExistingMappings()
    {
        $mapping = new TagMapping('/path1', 'tag1');

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeRootTagMapping($mapping);
    }

    public function testRemoveUndefinedRootTagMappings()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));

        $this->rootPackageFile->addTagMapping($mapping = new TagMapping('/path1', 'tag1'));
        $this->rootPackageFile->addTagMapping($undefined = new TagMapping('/path6', 'foobar'));

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping) {
                $tagMappings = $rootPackageFile->getTagMappings();

                PHPUnit_Framework_Assert::assertCount(1, $tagMappings);
                PHPUnit_Framework_Assert::assertSame($mapping, $tagMappings[0]);
            }));

        $this->manager->removeUndefinedRootTagMappings();
    }

    public function testRemoveUndefinedRootTagMappingsIgnoresIfNoneFound()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));

        $this->rootPackageFile->addTagMapping($mapping = new TagMapping('/path1', 'tag1'));

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeUndefinedRootTagMappings();
    }

    public function testClearRootTagMapping()
    {
        $mapping1 = new TagMapping('/path1', 'tag1');
        $mapping2 = new TagMapping('/path2', 'tag2');

        $this->rootPackageFile->addTagMapping($mapping1);
        $this->rootPackageFile->addTagMapping($mapping2);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping2) {
                $tagMappings = $rootPackageFile->getTagMappings();

                PHPUnit_Framework_Assert::assertCount(0, $tagMappings);
            }));

        $this->manager->clearRootTagMappings();
    }

    public function testClearRootTagMappingIgnoresEmptyMappings()
    {
        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->clearRootTagMappings();
    }

    public function testGetEnabledPackageTagMappings()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));
        $this->packageFile2->addTagDefinition(new TagDefinition('tag3'));

        $this->installInfo1->addEnabledTagMapping($mapping1 = new TagMapping('/path1', 'tag1'));
        $this->installInfo1->addEnabledTagMapping($mapping2 = new TagMapping('/path2', 'tag2'));
        $this->installInfo2->addEnabledTagMapping($mapping3 = new TagMapping('/path3', 'tag1'));
        $this->installInfo2->addEnabledTagMapping($mapping4 = new TagMapping('/path4', 'tag3'));
        $this->installInfo3->addEnabledTagMapping($mapping5 = new TagMapping('/path5', 'tag1'));

        $this->installInfo1->addEnabledTagMapping($undefinedMapping = new TagMapping('/path6', 'foobar'));
        $this->rootPackageFile->addTagMapping($rootMapping = new TagMapping('/path7', 'tag1'));
        $this->packageFile1->addTagMapping($newMapping = new TagMapping('/path8', 'tag1'));
        $this->installInfo1->addDisabledTagMapping($disabledMapping = new TagMapping('/path9', 'tag1'));

        $this->initManager();

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
            $mapping5,
        ), $this->manager->getEnabledPackageTagMappings());

        $this->assertSame(array($mapping1, $mapping3, $mapping5), $this->manager->getEnabledPackageTagMappings('tag1'));
        $this->assertSame(array($mapping1, $mapping2), $this->manager->getEnabledPackageTagMappings(null, 'package1'));
        $this->assertSame(array($mapping1), $this->manager->getEnabledPackageTagMappings('tag1', 'package1'));
        $this->assertSame(array($mapping1, $mapping3), $this->manager->getEnabledPackageTagMappings('tag1', array('package1', 'package2')));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\UndefinedTagException
     * @expectedExceptionMessage foobar
     */
    public function testGetEnabledPackageTagMappingsFailsIfTagNotDefined()
    {
        $this->initManager();

        $this->manager->getEnabledPackageTagMappings('foobar');
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
     * @expectedExceptionMessage foobar
     */
    public function testGetEnabledPackageTagMappingsFailsIfInvalidPackage()
    {
        $this->initManager();

        $this->manager->getEnabledPackageTagMappings(null, array('package1', 'foobar'));
    }

    public function testGetDisabledPackageTagMappings()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));
        $this->packageFile2->addTagDefinition(new TagDefinition('tag3'));

        $this->installInfo1->addDisabledTagMapping($mapping1 = new TagMapping('/path1', 'tag1'));
        $this->installInfo1->addDisabledTagMapping($mapping2 = new TagMapping('/path2', 'tag2'));
        $this->installInfo2->addDisabledTagMapping($mapping3 = new TagMapping('/path3', 'tag1'));
        $this->installInfo2->addDisabledTagMapping($mapping4 = new TagMapping('/path4', 'tag3'));
        $this->installInfo3->addDisabledTagMapping($mapping5 = new TagMapping('/path5', 'tag1'));

        $this->installInfo1->addDisabledTagMapping($undefinedMapping = new TagMapping('/path6', 'foobar'));
        $this->rootPackageFile->addTagMapping($rootMapping = new TagMapping('/path7', 'tag1'));
        $this->packageFile1->addTagMapping($newMapping = new TagMapping('/path8', 'tag1'));
        $this->installInfo1->addEnabledTagMapping($enabledMapping = new TagMapping('/path9', 'tag1'));

        $this->initManager();

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
            $mapping5,
        ), $this->manager->getDisabledPackageTagMappings());

        $this->assertSame(array($mapping1, $mapping3, $mapping5), $this->manager->getDisabledPackageTagMappings('tag1'));
        $this->assertSame(array($mapping1, $mapping2), $this->manager->getDisabledPackageTagMappings(null, 'package1'));
        $this->assertSame(array($mapping1), $this->manager->getDisabledPackageTagMappings('tag1', 'package1'));
        $this->assertSame(array($mapping1, $mapping3), $this->manager->getDisabledPackageTagMappings('tag1', array('package1', 'package2')));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\UndefinedTagException
     * @expectedExceptionMessage foobar
     */
    public function testGetDisabledPackageTagMappingsFailsIfTagNotDefined()
    {
        $this->initManager();

        $this->manager->getDisabledPackageTagMappings('foobar');
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
     * @expectedExceptionMessage foobar
     */
    public function testGetDisabledPackageTagMappingsFailsIfInvalidPackage()
    {
        $this->initManager();

        $this->manager->getDisabledPackageTagMappings(null, array('package1', 'foobar'));
    }

    public function testGetNewPackageTagMappings()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));
        $this->packageFile2->addTagDefinition(new TagDefinition('tag3'));

        $this->packageFile1->addTagMapping($mapping1 = new TagMapping('/path1', 'tag1'));
        $this->packageFile1->addTagMapping($mapping2 = new TagMapping('/path2', 'tag2'));
        $this->packageFile2->addTagMapping($mapping3 = new TagMapping('/path3', 'tag1'));
        $this->packageFile2->addTagMapping($mapping4 = new TagMapping('/path4', 'tag3'));
        $this->packageFile3->addTagMapping($mapping5 = new TagMapping('/path5', 'tag1'));

        $this->packageFile1->addTagMapping($undefinedMapping = new TagMapping('/path6', 'foobar'));
        $this->rootPackageFile->addTagMapping($rootMapping = new TagMapping('/path7', 'tag1'));

        // The package file and the install info are loaded from different
        // sources, so we only have equal, but not the same objects
        $this->packageFile1->addTagMapping($enabledMapping = new TagMapping('/path8', 'tag1'));
        $this->packageFile2->addTagMapping($disabledMapping = new TagMapping('/path9', 'tag1'));
        $this->installInfo1->addEnabledTagMapping(clone $enabledMapping);
        $this->installInfo2->addDisabledTagMapping(clone $disabledMapping);

        $this->initManager();

        $this->assertSame(array(
            $mapping1,
            $mapping2,
            $mapping3,
            $mapping4,
            $mapping5,
        ), $this->manager->getNewPackageTagMappings());

        $this->assertSame(array($mapping1, $mapping3, $mapping5), $this->manager->getNewPackageTagMappings('tag1'));
        $this->assertSame(array($mapping1, $mapping2), $this->manager->getNewPackageTagMappings(null, 'package1'));
        $this->assertSame(array($mapping1), $this->manager->getNewPackageTagMappings('tag1', 'package1'));
        $this->assertSame(array($mapping1, $mapping3), $this->manager->getNewPackageTagMappings('tag1', array('package1', 'package2')));
    }

    public function testEnableTagMapping()
    {
        $mapping1 = new TagMapping('/path1', 'tag1');
        $mapping2 = new TagMapping('/path2', 'tag2');

        $this->installInfo1->addEnabledTagMapping($mapping1);

        // compare by value
        $this->packageFile1->addTagMapping(clone $mapping2);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping1, $mapping2) {
                $installInfo = $rootPackageFile->getInstallInfo('package1');
                $tagMappings = $installInfo->getEnabledTagMappings();

                PHPUnit_Framework_Assert::assertCount(2, $tagMappings);
                PHPUnit_Framework_Assert::assertSame($mapping1, $tagMappings[0]);
                PHPUnit_Framework_Assert::assertSame($mapping2, $tagMappings[1]);
            }));

        $this->manager->enablePackageTagMapping('package1', $mapping2);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\NoSuchTagMappingException
     * @expectedExceptionMessage package1/puli.json
     */
    public function testEnableTagMappingFailsIfMappingNotFound()
    {
        $mapping = new TagMapping('/path1', 'tag1');

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enablePackageTagMapping('package1', $mapping);
    }

    public function testDisableTagMapping()
    {
        $mapping1 = new TagMapping('/path1', 'tag1');
        $mapping2 = new TagMapping('/path2', 'tag2');

        $this->installInfo1->addDisabledTagMapping($mapping1);

        // compare by value
        $this->packageFile1->addTagMapping(clone $mapping2);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping1, $mapping2) {
                $installInfo = $rootPackageFile->getInstallInfo('package1');
                $tagMappings = $installInfo->getDisabledTagMappings();

                PHPUnit_Framework_Assert::assertCount(2, $tagMappings);
                PHPUnit_Framework_Assert::assertSame($mapping1, $tagMappings[0]);
                PHPUnit_Framework_Assert::assertSame($mapping2, $tagMappings[1]);
            }));

        $this->manager->disablePackageTagMapping('package1', $mapping2);
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\NoSuchTagMappingException
     * @expectedExceptionMessage package1/puli.json
     */
    public function testDisableTagMappingFailsIfMappingNotFound()
    {
        $mapping = new TagMapping('/path1', 'tag1');

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disablePackageTagMapping('package1', $mapping);
    }

    public function testRemoveUndefinedPackageTagMappings()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));

        $this->installInfo1->addEnabledTagMapping($mapping1 = new TagMapping('/path1', 'tag1'));
        $this->installInfo2->addDisabledTagMapping($mapping2 = new TagMapping('/path2', 'tag1'));
        $this->installInfo1->addEnabledTagMapping($undefined1 = new TagMapping('/path3', 'foobar'));
        $this->installInfo2->addDisabledTagMapping($undefined2 = new TagMapping('/path4', 'foobar'));

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping1, $mapping2) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $tagMappings1 = $installInfo1->getEnabledTagMappings();
                $tagMappings2 = $installInfo2->getDisabledTagMappings();

                PHPUnit_Framework_Assert::assertCount(1, $tagMappings1);
                PHPUnit_Framework_Assert::assertSame($mapping1, $tagMappings1[0]);

                PHPUnit_Framework_Assert::assertCount(1, $tagMappings2);
                PHPUnit_Framework_Assert::assertSame($mapping2, $tagMappings2[0]);
            }));

        $this->manager->removeUndefinedPackageTagMappings();
    }

    public function testRemoveUndefinedPackageTagMappingsForPackage()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));

        $this->installInfo1->addEnabledTagMapping($mapping1 = new TagMapping('/path1', 'tag1'));
        $this->installInfo2->addDisabledTagMapping($mapping2 = new TagMapping('/path2', 'tag1'));
        $this->installInfo1->addEnabledTagMapping($undefined1 = new TagMapping('/path3', 'foobar'));
        $this->installInfo2->addDisabledTagMapping($undefined2 = new TagMapping('/path4', 'foobar'));

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping1, $undefined1, $mapping2) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $tagMappings1 = $installInfo1->getEnabledTagMappings();
                $tagMappings2 = $installInfo2->getDisabledTagMappings();

                PHPUnit_Framework_Assert::assertCount(2, $tagMappings1);
                PHPUnit_Framework_Assert::assertSame($mapping1, $tagMappings1[0]);
                PHPUnit_Framework_Assert::assertSame($undefined1, $tagMappings1[1]);

                PHPUnit_Framework_Assert::assertCount(1, $tagMappings2);
                PHPUnit_Framework_Assert::assertSame($mapping2, $tagMappings2[0]);
            }));

        $this->manager->removeUndefinedPackageTagMappings('package2');
    }

    public function testRemoveUndefinedPackageTagMappingsForPackages()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));

        $this->installInfo1->addEnabledTagMapping($mapping1 = new TagMapping('/path1', 'tag1'));
        $this->installInfo2->addDisabledTagMapping($mapping2 = new TagMapping('/path2', 'tag1'));
        $this->installInfo3->addEnabledTagMapping($mapping3 = new TagMapping('/path3', 'tag1'));
        $this->installInfo1->addEnabledTagMapping($undefined1 = new TagMapping('/path4', 'foobar'));
        $this->installInfo2->addDisabledTagMapping($undefined2 = new TagMapping('/path5', 'foobar'));
        $this->installInfo3->addEnabledTagMapping($undefined3 = new TagMapping('/path6', 'foobar'));

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($mapping1, $mapping2, $mapping3, $undefined3) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $installInfo3 = $rootPackageFile->getInstallInfo('package3');
                $tagMappings1 = $installInfo1->getEnabledTagMappings();
                $tagMappings2 = $installInfo2->getDisabledTagMappings();
                $tagMappings3 = $installInfo3->getEnabledTagMappings();

                PHPUnit_Framework_Assert::assertCount(1, $tagMappings1);
                PHPUnit_Framework_Assert::assertSame($mapping1, $tagMappings1[0]);

                PHPUnit_Framework_Assert::assertCount(1, $tagMappings2);
                PHPUnit_Framework_Assert::assertSame($mapping2, $tagMappings2[0]);

                PHPUnit_Framework_Assert::assertCount(2, $tagMappings3);
                PHPUnit_Framework_Assert::assertSame($mapping3, $tagMappings3[0]);
                PHPUnit_Framework_Assert::assertSame($undefined3, $tagMappings3[1]);
            }));

        $this->manager->removeUndefinedPackageTagMappings(array('package1', 'package2'));
    }

    public function testRemoveUndefinedPackageTagMappingsIgnoresIfNoneFound()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));

        $this->installInfo1->addEnabledTagMapping($mapping1 = new TagMapping('/path1', 'tag1'));
        $this->installInfo2->addDisabledTagMapping($mapping2 = new TagMapping('/path2', 'tag1'));

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeUndefinedPackageTagMappings();
    }

    public function testGetTagDefinitions()
    {
        $this->rootPackageFile->addTagDefinition($definition1 = new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition($definition2 = new TagDefinition('tag2'));
        $this->packageFile2->addTagDefinition($definition3 = new TagDefinition('tag3'));
        $this->packageFile3->addTagDefinition($definition4 = new TagDefinition('tag4'));

        $this->initManager();

        $this->assertSame(array(
            $definition1,
            $definition2,
            $definition3,
            $definition4,
        ), $this->manager->getTagDefinitions());

        $this->assertSame(array($definition2), $this->manager->getTagDefinitions('package1'));
        $this->assertSame(array($definition2, $definition3), $this->manager->getTagDefinitions(array('package1', 'package2')));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Package\NoSuchPackageException
     * @expectedExceptionMessage foobar
     */
    public function testGetTagDefinitionsFailsIfUnknownPackage()
    {
        $this->initManager();

        $this->manager->getTagDefinitions('foobar');
    }

    public function testGetTagDefinition()
    {
        $this->rootPackageFile->addTagDefinition($definition1 = new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition($definition2 = new TagDefinition('tag2'));
        $this->packageFile2->addTagDefinition($definition3 = new TagDefinition('tag3'));
        $this->packageFile3->addTagDefinition($definition4 = new TagDefinition('tag4'));

        $this->initManager();

        $this->assertSame($definition1, $this->manager->getTagDefinition('tag1'));
        $this->assertSame($definition2, $this->manager->getTagDefinition('tag2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\UndefinedTagException
     * @expectedExceptionMessage foobar
     */
    public function testGetTagDefinitionFailsIfUndefinedTag()
    {
        $this->initManager();

        $this->manager->getTagDefinition('foobar');
    }

    public function testFindTagDefinitions()
    {
        $this->rootPackageFile->addTagDefinition($definition1 = new TagDefinition('tag1'));
        $this->rootPackageFile->addTagDefinition($definition2 = new TagDefinition('1foo'));
        $this->packageFile1->addTagDefinition($definition3 = new TagDefinition('tag2'));
        $this->packageFile2->addTagDefinition($definition4 = new TagDefinition('2foo'));
        $this->packageFile3->addTagDefinition($definition5 = new TagDefinition('3foo'));

        $this->initManager();

        $this->assertSame(array($definition1, $definition3), $this->manager->findTagDefinitions('tag*'));
        $this->assertSame(array($definition2, $definition4, $definition5), $this->manager->findTagDefinitions('*foo'));
        $this->assertSame(array($definition3, $definition4), $this->manager->findTagDefinitions('*2*'));
        $this->assertSame(array(), $this->manager->findTagDefinitions('foobar'));

        $this->assertSame(array($definition3), $this->manager->findTagDefinitions('tag*', 'package1'));
        $this->assertSame(array($definition2, $definition4), $this->manager->findTagDefinitions('*foo', array('root', 'package2')));
    }

    public function testIsTagDefined()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));

        $this->initManager();

        $this->assertTrue($this->manager->isTagDefined('tag1'));
        $this->assertTrue($this->manager->isTagDefined('tag2'));
        $this->assertFalse($this->manager->isTagDefined('foobar'));
    }

    public function testGetRootTagDefinitions()
    {
        $this->rootPackageFile->addTagDefinition($definition1 = new TagDefinition('tag1'));
        $this->rootPackageFile->addTagDefinition($definition2 = new TagDefinition('tag2'));
        $this->packageFile1->addTagDefinition($definition3 = new TagDefinition('tag3'));

        $this->initManager();

        $this->assertSame(array($definition1, $definition2), $this->manager->getRootTagDefinitions());
    }

    public function testGetRootTagDefinition()
    {
        $this->rootPackageFile->addTagDefinition($definition1 = new TagDefinition('tag1'));
        $this->rootPackageFile->addTagDefinition($definition2 = new TagDefinition('tag2'));

        $this->initManager();

        $this->assertSame($definition2, $this->manager->getRootTagDefinition('tag2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\UndefinedTagException
     * @expectedExceptionMessage foobar
     */
    public function testGetRootTagDefinitionFailsIfUndefinedTag()
    {
        $this->initManager();

        $this->manager->getRootTagDefinition('foobar');
    }

    public function testFindRootTagDefinitions()
    {
        $this->rootPackageFile->addTagDefinition($definition1 = new TagDefinition('tag1'));
        $this->rootPackageFile->addTagDefinition($definition2 = new TagDefinition('1foo'));
        $this->rootPackageFile->addTagDefinition($definition3 = new TagDefinition('tag2'));
        $this->packageFile2->addTagDefinition($definition4 = new TagDefinition('2foo'));

        $this->initManager();

        $this->assertSame(array($definition1, $definition3), $this->manager->findRootTagDefinitions('tag*'));
        $this->assertSame(array($definition2), $this->manager->findRootTagDefinitions('*foo'));
        $this->assertSame(array($definition1, $definition2), $this->manager->findRootTagDefinitions('*1*'));
        $this->assertSame(array(), $this->manager->findRootTagDefinitions('foobar'));
    }

    public function testAddRootTagDefinition()
    {
        $definition1 = new TagDefinition('tag1');
        $definition2 = new TagDefinition('tag2');

        $this->rootPackageFile->addTagDefinition($definition1);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($definition1, $definition2) {
                $definitions = $rootPackageFile->getTagDefinitions();

                PHPUnit_Framework_Assert::assertCount(2, $definitions);
                PHPUnit_Framework_Assert::assertSame($definition1, $definitions[0]);
                PHPUnit_Framework_Assert::assertSame($definition2, $definitions[1]);
            }));

        $this->assertFalse($this->manager->isTagDefined('tag2'));

        $this->manager->addRootTagDefinition($definition2);

        $this->assertTrue($this->manager->isTagDefined('tag2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\DuplicateTagException
     */
    public function testAddRootTagDefinitionFailsIfAlreadyDefinedInRoot()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootTagDefinition(new TagDefinition('tag1'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Tag\DuplicateTagException
     */
    public function testAddRootTagDefinitionFailsIfAlreadyDefinedInPackage()
    {
        $this->packageFile1->addTagDefinition(new TagDefinition('tag1'));

        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootTagDefinition(new TagDefinition('tag1'));
    }

    public function testRemoveRootTagDefinition()
    {
        $definition1 = new TagDefinition('tag1');
        $definition2 = new TagDefinition('tag2');

        $this->rootPackageFile->addTagDefinition($definition1);
        $this->rootPackageFile->addTagDefinition($definition2);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($definition2) {
                $definitions = $rootPackageFile->getTagDefinitions();

                PHPUnit_Framework_Assert::assertCount(1, $definitions);
                PHPUnit_Framework_Assert::assertSame($definition2, $definitions[0]);
            }));

        $this->assertTrue($this->manager->isTagDefined('tag1'));

        $this->manager->removeRootTagDefinition('tag1');

        $this->assertFalse($this->manager->isTagDefined('tag1'));
    }

    public function testRemoveRootTagDefinitionIgnoresNonExisting()
    {
        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeRootTagDefinition('foobar');
    }

    public function testHasRootTagDefinition()
    {
        $this->rootPackageFile->addTagDefinition(new TagDefinition('tag1'));
        $this->packageFile1->addTagDefinition(new TagDefinition('tag2'));

        $this->initManager();

        $this->assertTrue($this->manager->hasRootTagDefinition('tag1'));
        $this->assertFalse($this->manager->hasRootTagDefinition('tag2'));
        $this->assertFalse($this->manager->hasRootTagDefinition('foobar'));
    }

    public function testClearRootTagDefinitions()
    {
        $definition1 = new TagDefinition('tag1');
        $definition2 = new TagDefinition('tag2');

        $this->rootPackageFile->addTagDefinition($definition1);
        $this->rootPackageFile->addTagDefinition($definition2);

        $this->initManager();

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $definitions = $rootPackageFile->getTagDefinitions();

                PHPUnit_Framework_Assert::assertCount(0, $definitions);
            }));

        $this->assertTrue($this->manager->isTagDefined('tag1'));
        $this->assertTrue($this->manager->isTagDefined('tag2'));

        $this->manager->clearRootTagDefinitions();

        $this->assertFalse($this->manager->isTagDefined('tag1'));
        $this->assertFalse($this->manager->isTagDefined('tag2'));
    }

    public function testClearRootTagDefinitionsIgnoresIfNoDefinitions()
    {
        $this->initManager();

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->clearRootTagDefinitions();
    }

    private function initManager()
    {
        $this->manager = new TagManager($this->environment, $this->packages, $this->packageFileStorage);
    }
}
