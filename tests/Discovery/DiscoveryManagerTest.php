<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Discovery;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Tests\ManagerTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManagerTest extends ManagerTestCase
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
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var DiscoveryManager
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/DiscoveryManagerTest'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->packageDir1 = $this->tempDir.'/package1';
        $this->packageDir2 = $this->tempDir.'/package2';
        $this->packageDir3 = $this->tempDir.'/package3';

        $this->packageFile1 = new PackageFile();
        $this->packageFile2 = new PackageFile();
        $this->packageFile3 = new PackageFile();

        $this->installInfo1 = new InstallInfo('package1', $this->packageDir1);
        $this->installInfo2 = new InstallInfo('package2', $this->packageDir2);
        $this->installInfo3 = new InstallInfo('package3', $this->packageDir3);

        $this->packages = new PackageCollection();

        $this->logger = $this->getMock('Psr\Log\LoggerInterface');

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

    public function testAddBindingType()
    {
        $this->initDefaultManager();

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingType), $types);
            }));

        $this->manager->addBindingType($bindingType);
    }

    /**
     * @expectedException \Puli\Discovery\Api\DuplicateTypeException
     */
    public function testAddBindingTypeFailsIfAlreadyDefined()
    {
        $this->initDefaultManager();

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->packageFile1->addTypeDescriptor($bindingType);

        $this->discovery->expects($this->never())
            ->method('define');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBindingType($bindingType);
    }

    public function testRemoveBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('undefine')
            ->with('my/type');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeIgnoresNonExistingTypes()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeIgnoresTypesInInstalledPackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeBindingType('my/type');
    }

    public function testGetBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = new BindingTypeDescriptor('my/type3'));
        $this->packageFile3->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type4'));

        $this->assertSame(array(
            $type1,
            $type2,
            $type3,
            $type4,
        ), $this->manager->getBindingTypes());

        $this->assertSame(array($type2), $this->manager->getBindingTypes('package1'));
        $this->assertSame(array($type2, $type3), $this->manager->getBindingTypes(array('package1', 'package2')));
    }

    public function testAddBinding()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertCount(1, $bindings);
                PHPUnit_Framework_Assert::assertSame('/path', $bindings[0]->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/type', $bindings[0]->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array('param' => 'value'), $bindings[0]->getParameters());
                PHPUnit_Framework_Assert::assertSame('xpath', $bindings[0]->getLanguage());
            }));

        $this->manager->addBinding('/path', 'my/type', array('param' => 'value'), 'xpath');
    }

    public function testAddBindingDoesNotAddBindingDuplicatedInPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertCount(1, $bindings);
                PHPUnit_Framework_Assert::assertSame('/path', $bindings[0]->getQuery());
                PHPUnit_Framework_Assert::assertSame('my/type', $bindings[0]->getTypeName());
                PHPUnit_Framework_Assert::assertSame(array(), $bindings[0]->getParameters());
                PHPUnit_Framework_Assert::assertSame('glob', $bindings[0]->getLanguage());
            }));

        $this->manager->addBinding('/path', 'my/type');
    }

    /**
     * @expectedException \Puli\Discovery\Api\NoSuchTypeException
     */
    public function testAddBindingFailsIfTypeNotDefined()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding('/path', 'my/type', array('param' => 'value'), 'xpath');
    }

    public function testGetBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type1'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type2'));
        $this->packageFile2->addBindingDescriptor($binding3 = BindingDescriptor::create('/path3', 'my/type3'));
        $this->packageFile3->addBindingDescriptor($binding4 = BindingDescriptor::create('/path4', 'my/type4'));

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
            $binding4,
        ), $this->manager->getBindings());

        $this->assertSame(array($binding2), $this->manager->getBindings('package1'));
        $this->assertSame(array($binding2, $binding3), $this->manager->getBindings(array('package1', 'package2')));
    }

    public function testBuildDiscovery()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryOnlyIncludesEnabledBindingsOfInstalledPackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding3 = BindingDescriptor::create('/path3', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());
        $this->installInfo1->addDisabledBindingUuid($binding3->getUuid());
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path2', 'my/type', array(), 'glob');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryAddsDuplicateBindingsOnlyOnce()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding2 = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding2->getUuid());
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningIfDuplicateType()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile2->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('define');

        $this->logger->expects($this->once())
            ->method('warning');

        $this->manager->buildDiscovery();
    }

    private function initDefaultManager()
    {
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));
        $this->packages->add(new Package($this->packageFile1, $this->packageDir1, $this->installInfo1));
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2, $this->installInfo2));
        $this->packages->add(new Package($this->packageFile3, $this->packageDir3, $this->installInfo3));

        $this->manager = new DiscoveryManager($this->environment, $this->packages, $this->packageFileStorage, $this->logger);
    }
}
