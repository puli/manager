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
use Puli\Discovery\Api\Binding\BindingType;
use Puli\Discovery\Api\Binding\MissingParameterException;
use Puli\Discovery\Api\NoQueryMatchesException;
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Discovery\BindingState;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeState;
use Puli\RepositoryManager\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Package\RootPackage;
use Puli\RepositoryManager\Tests\ManagerTestCase;
use Rhumsaa\Uuid\Uuid;
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

        $this->assertTrue($bindingType->isEnabled());
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

        $this->assertFalse($bindingType->isEnabled());
    }

    public function testAddBindingTypeAddsHeldBackBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingType), $types);
            }));

        $this->manager->addBindingType($bindingType);
    }

    public function testAddBindingTypeAddsDuplicateHeldBackBindingsOnlyOnce()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding);
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingType), $types);
            }));

        $this->manager->addBindingType($bindingType);
    }

    public function testAddBindingTypeUndefinesTypeIfSavingFails()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($existingType = new BindingTypeDescriptor('my/existing'));

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('undefine')
            ->with('my/type');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->addBindingType($bindingType);
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existingType), $this->rootPackageFile->getTypeDescriptors());

        $this->assertTrue($existingType->isEnabled());
        $this->assertFalse($bindingType->isEnabled());
    }

    public function testRemoveBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

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

        $this->assertTrue($bindingType->isUnloaded());
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

        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeBindingType('my/type');

        $this->assertTrue($bindingType->isEnabled());
    }

    public function testRemoveBindingTypeDefinesTypeIfResolvingDuplication()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType2 = clone $bindingType1);

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType1->toBindingType());

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');

        $this->assertTrue($bindingType1->isUnloaded());
        $this->assertTrue($bindingType2->isEnabled());
    }

    public function testRemoveBindingTypeDoesNotDefineTypeIfStillDuplicated()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType2 = clone $bindingType1);
        $this->packageFile2->addTypeDescriptor($bindingType3 = clone $bindingType1);

        $this->discovery->expects($this->never())
            ->method('define');

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');

        $this->assertTrue($bindingType1->isUnloaded());
        $this->assertTrue($bindingType2->isDuplicate());
        $this->assertTrue($bindingType3->isDuplicate());
    }

    public function testRemoveBindingTypeUnbindsCorrespondingBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->once())
            ->method('undefine')
            ->with('my/type');

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeUnbindsCorrespondingDuplicatedBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding);
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('undefine')
            ->with('my/type');

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeAddsFormerlyIgnoredBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType);
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeDoesNotAddFormerlyIgnoredBindingsIfStillDuplicated()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType);
        $this->packageFile2->addTypeDescriptor($bindingType);
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('define');

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeDoesNotEmitWarningForRemovedDuplicateType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->logger->expects($this->never())
            ->method('warning');

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeEmitsWarningIfDuplicatedMoreThanOnce()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile2->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->logger->expects($this->once())
            ->method('warning');

        $this->discovery->expects($this->never())
            ->method('define');

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

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

    public function testGetEnabledBindingTypesDoesNotIncludeDuplicateTypes()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2);
        $this->packageFile2->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type3'));

        $this->assertEquals(array($type1, $type4), $this->manager->getBindingTypes());
        $this->assertEquals(array($type1), $this->manager->getBindingTypes('package1'));
        $this->assertEquals(array($type4), $this->manager->getBindingTypes('package2'));
        $this->assertEquals(array($type1, $type4), $this->manager->getBindingTypes(array('package1', 'package2')));
    }

    public function testGetDuplicateBindingTypesDoesNotIncludeEnabledTypes()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2);
        $this->packageFile2->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type3'));

        $this->assertEquals(array($type2, $type3), $this->manager->getBindingTypes(null, BindingTypeState::DUPLICATE));
        $this->assertEquals(array($type2), $this->manager->getBindingTypes('package1', BindingTypeState::DUPLICATE));
        $this->assertEquals(array($type3), $this->manager->getBindingTypes('package2', BindingTypeState::DUPLICATE));
        $this->assertEquals(array($type2, $type3), $this->manager->getBindingTypes(array('package1', 'package2'), BindingTypeState::DUPLICATE));
    }

    public function testAddBinding()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));

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
                PHPUnit_Framework_Assert::assertTrue($bindings[0]->isEnabled());
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
                PHPUnit_Framework_Assert::assertTrue($bindings[0]->isEnabled());
            }));

        $this->manager->addBinding('/path', 'my/type');
    }

    public function testAddBindingDoesNotAddBindingDuplicatedInRoot()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

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

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\TypeNotEnabledException
     */
    public function testAddBindingFailsIfTypeNotEnabled()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding('/path', 'my/type', array('param' => 'value'), 'xpath');
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\MissingParameterException
     */
    public function testAddBindingFailsIfMissingParameters()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', true),
        )));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding('/path', 'my/type');
    }

    /**
     * @expectedException \Puli\Discovery\Api\NoQueryMatchesException
     */
    public function testAddBindingFailsIfNoResourcesFound()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->willThrowException(new NoQueryMatchesException());

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding('/path', 'my/type');
    }

    public function testAddBindingUnbindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($existing = BindingDescriptor::create('/existing', 'my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->addBinding('/path', 'my/type', array('param' => 'value'), 'xpath');
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existing), $this->rootPackageFile->getBindingDescriptors());
    }

    public function testRemoveBinding()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindings);
            }));

        // Verify that the state is updated
        $binding->setState(BindingState::ENABLED);

        $this->manager->removeBinding($binding->getUuid());

        $this->assertTrue($binding->isUnloaded());
    }

    public function testRemoveBindingIgnoresNonExistingBindings()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeBinding(Uuid::uuid4());
    }

    public function testRemoveBindingIgnoresBindingsInPackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeBinding($binding->getUuid());

        $this->assertTrue($binding->isEnabled());
    }

    public function testRemoveBindingDoesNotUnbindDuplicatedBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindings);
            }));

        $binding1->setState(BindingState::ENABLED);
        $binding2->setState(BindingState::ENABLED);

        $this->manager->removeBinding($binding1->getUuid());

        $this->assertFalse($binding1->isEnabled());
        $this->assertTrue($binding2->isEnabled());
    }

    public function testRemoveHeldBackBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindings);
            }));

        $this->manager->removeBinding($binding->getUuid());
    }

    public function testRemoveIgnoredBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindings);
            }));

        $this->manager->removeBinding($binding->getUuid());
    }

    public function testAddBindingWithDuplicateCanBeUndoneWithRemoveBinding()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->exactly(2))
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile);

        // Duplicate, not bound
        $this->manager->addBinding('/path', 'my/type');

        // Duplicate removed, not unbound
        $this->manager->removeBinding($binding->getUuid());

        // Duplicate is still enabled
        $this->assertTrue($binding->isEnabled());
    }

    public function testEnableBindingBindsIfUndecided()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('package1');
                $enabledBindingUuids = $installInfo->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding->getUuid()), $enabledBindingUuids);
            }));

        $this->manager->enableBinding($binding->getUuid());

        $this->assertTrue($binding->isEnabled());
    }

    public function testEnableBindingBindsIfDisabled()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('package1');
                $enabledBindingUuids = $installInfo->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding->getUuid()), $enabledBindingUuids);
            }));

        $this->manager->enableBinding($binding->getUuid());

        $this->assertTrue($binding->isEnabled());
    }

    public function testEnableBindingDoesNothingIfAlreadyEnabled()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding->getUuid());

        $this->assertTrue($binding->isEnabled());
    }

    public function testEnableBindingAppliedToAllPackagesByDefault()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $enabledBindingUuids1 = $installInfo1->getEnabledBindingUuids();
                $enabledBindingUuids2 = $installInfo2->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids2);
            }));

        $this->manager->enableBinding($binding1->getUuid());

        $this->assertTrue($binding1->isEnabled());
        $this->assertTrue($binding2->isEnabled());
    }

    public function testEnableBindingForOnePackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $enabledBindingUuids1 = $installInfo1->getEnabledBindingUuids();
                $enabledBindingUuids2 = $installInfo2->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array(), $enabledBindingUuids2);
            }));

        $this->manager->enableBinding($binding1->getUuid(), 'package1');

        $this->assertTrue($binding1->isEnabled());
        $this->assertFalse($binding2->isEnabled());
    }

    public function testEnableBindingForMultiplePackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);
        $this->packageFile3->addBindingDescriptor($binding3 = clone $binding1);

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1, $binding2) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $installInfo3 = $rootPackageFile->getInstallInfo('package3');
                $enabledBindingUuids1 = $installInfo1->getEnabledBindingUuids();
                $enabledBindingUuids2 = $installInfo2->getEnabledBindingUuids();
                $enabledBindingUuids3 = $installInfo3->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array($binding2->getUuid()), $enabledBindingUuids2);
                PHPUnit_Framework_Assert::assertSame(array(), $enabledBindingUuids3);
            }));

        $this->manager->enableBinding($binding1->getUuid(), array('package1', 'package2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\NoSuchBindingException
     * @expectedExceptionMessage 8546da2c-dfec-48be-8cd3-93798c41b72f
     */
    public function testEnableBindingFailsIfBindingNotFound()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding(Uuid::fromString('8546da2c-dfec-48be-8cd3-93798c41b72f'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingInRootPackageOnly()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingInRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding);

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingHeldBack()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingIgnored()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding->getUuid());
    }

    public function testEnableBindingUnbindsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($existing = BindingDescriptor::create('/existing', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addEnabledBindingUuid($existing->getUuid());
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->enableBinding($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existing->getUuid()), $this->installInfo1->getEnabledBindingUuids());
        $this->assertSame(array($binding->getUuid()), $this->installInfo1->getDisabledBindingUuids());
    }

    public function testEnableBindingRestoresDisabledBindingsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->enableBinding($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array(), $this->installInfo1->getEnabledBindingUuids());
        $this->assertSame(array($binding->getUuid()), $this->installInfo1->getDisabledBindingUuids());
    }

    public function testDisableBindingUnbindsIfEnabled()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('package1');
                $disabledBindingUuids = $installInfo->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding->getUuid()), $disabledBindingUuids);
            }));

        $this->manager->disableBinding($binding->getUuid());

        $this->assertTrue($binding->isDisabled());
    }

    public function testDisableBindingDoesNotUnbindIfUndecided()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('package1');
                $disabledBindingUuids = $installInfo->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding->getUuid()), $disabledBindingUuids);
            }));

        $this->manager->disableBinding($binding->getUuid());

        $this->assertTrue($binding->isDisabled());
    }

    public function testDisableBindingDoesNothingIfAlreadyDisabled()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding->getUuid());

        $this->assertTrue($binding->isDisabled());
    }

    public function testDisableBindingAppliedToAllPackagesByDefault()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding2->getUuid());

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $disabledBindingUuids1 = $installInfo1->getDisabledBindingUuids();
                $disabledBindingUuids2 = $installInfo2->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $disabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $disabledBindingUuids2);
            }));

        $this->manager->disableBinding($binding1->getUuid());

        $this->assertTrue($binding1->isDisabled());
        $this->assertTrue($binding2->isDisabled());
    }

    public function testDisableBindingForOnePackageDoesNotUnbind()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding2->getUuid());

        // The other binding still exists
        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $disabledBindingUuids1 = $installInfo1->getDisabledBindingUuids();
                $disabledBindingUuids2 = $installInfo2->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $disabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array(), $disabledBindingUuids2);
            }));

        $this->manager->disableBinding($binding1->getUuid(), 'package1');

        $this->assertTrue($binding1->isDisabled());
        $this->assertFalse($binding2->isDisabled());
    }

    public function testDisableBindingForMultiplePackagesDoesNotUnbind()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);
        $this->packageFile3->addBindingDescriptor($binding3 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding2->getUuid());
        $this->installInfo3->addEnabledBindingUuid($binding3->getUuid());

        // One binding still exists
        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1, $binding2) {
                $installInfo1 = $rootPackageFile->getInstallInfo('package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('package2');
                $installInfo3 = $rootPackageFile->getInstallInfo('package3');
                $disabledBindingUuids1 = $installInfo1->getDisabledBindingUuids();
                $disabledBindingUuids2 = $installInfo2->getDisabledBindingUuids();
                $disabledBindingUuids3 = $installInfo3->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $disabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array($binding2->getUuid()), $disabledBindingUuids2);
                PHPUnit_Framework_Assert::assertSame(array(), $disabledBindingUuids3);
            }));

        $this->manager->disableBinding($binding1->getUuid(), array('package1', 'package2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\NoSuchBindingException
     * @expectedExceptionMessage 8546da2c-dfec-48be-8cd3-93798c41b72f
     */
    public function testDisableBindingFailsIfBindingNotFound()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding(Uuid::fromString('8546da2c-dfec-48be-8cd3-93798c41b72f'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingInRootPackageOnly()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingInRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding);

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingHeldBack()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingIgnored()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding->getUuid());
    }

    public function testDisableBindingRebindsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($existing = BindingDescriptor::create('/existing', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addDisabledBindingUuid($existing->getUuid());
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->disableBinding($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existing->getUuid()), $this->installInfo1->getDisabledBindingUuids());
        $this->assertSame(array($binding->getUuid()), $this->installInfo1->getEnabledBindingUuids());
    }

    public function testDisableBindingRestoresEnabledBindingsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->disableBinding($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array(), $this->installInfo1->getDisabledBindingUuids());
        $this->assertSame(array($binding->getUuid()), $this->installInfo1->getEnabledBindingUuids());
    }

    public function testGetEnabledBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding3 = BindingDescriptor::create('/path3', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding4 = BindingDescriptor::create('/path4', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding3->getUuid());
        $this->installInfo3->addEnabledBindingUuid($binding4->getUuid());

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
            $binding4,
        ), $this->manager->getBindings());

        $this->assertSame(array($binding2), $this->manager->getBindings('package1'));
        $this->assertSame(array($binding2, $binding3), $this->manager->getBindings(array('package1', 'package2')));
    }

    public function testGetEnabledBindingsMergesDuplicates()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding);
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->assertSame(array($binding), $this->manager->getBindings());

        $this->assertSame(array($binding), $this->manager->getBindings('root'));
        $this->assertSame(array($binding), $this->manager->getBindings('package1'));
    }

    public function testGetEnabledBindingsDoesNotIncludeHeldBackBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());

        $this->assertSame(array(), $this->manager->getBindings());
        $this->assertSame(array(), $this->manager->getBindings('package1'));
    }

    public function testGetEnabledBindingsDoesNotIncludeIgnoredBindings()
    {
        $this->initDefaultManager();

        // Duplicate type - disabled
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());

        $this->assertSame(array(), $this->manager->getBindings());
        $this->assertSame(array(), $this->manager->getBindings('package1'));
    }

    public function testGetEnabledBindingsDoesNotIncludeNewBindings()
    {
        $this->initDefaultManager();

        // neither enabled nor disabled
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array(), $this->manager->getBindings());
        $this->assertSame(array(), $this->manager->getBindings('package1'));
    }

    public function testGetEnabledBindingsDoesNotIncludeDisabledBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->assertSame(array(), $this->manager->getBindings());
        $this->assertSame(array(), $this->manager->getBindings('package1'));
    }

    public function testGetDisabledBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding3 = BindingDescriptor::create('/path3', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding1->getUuid());
        $this->installInfo2->addDisabledBindingUuid($binding2->getUuid());
        $this->installInfo3->addDisabledBindingUuid($binding3->getUuid());

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
        ), $this->manager->getBindings(null, BindingState::DISABLED));

        $this->assertSame(array($binding1), $this->manager->getBindings('package1', BindingState::DISABLED));
        $this->assertSame(array($binding1, $binding2), $this->manager->getBindings(array('package1', 'package2'), BindingState::DISABLED));
    }

    public function testGetDisabledBindingsDoesNotIncludeRootBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::DISABLED));
        $this->assertSame(array(), $this->manager->getBindings('root', BindingState::DISABLED));
    }

    public function testGetDisabledBindingsDoesNotIncludeHeldBackBindings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::DISABLED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::DISABLED));
    }

    public function testGetDisabledBindingsDoesNotIncludeIgnoredBindings()
    {
        $this->initDefaultManager();

        // Duplicate type - disabled
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::DISABLED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::DISABLED));
    }

    public function testGetDisabledBindingsDoesNotIncludeEnabledBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::DISABLED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::DISABLED));
    }

    public function testGetDisabledBindingsDoesNotIncludeNewBindings()
    {
        $this->initDefaultManager();

        // Neither enabled nor disabled
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::DISABLED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::DISABLED));
    }

    public function testGetUndecidedBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding3 = BindingDescriptor::create('/path3', 'my/type'));

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
        ), $this->manager->getBindings(null, BindingState::UNDECIDED));

        $this->assertSame(array($binding1), $this->manager->getBindings('package1', BindingState::UNDECIDED));
        $this->assertSame(array($binding1, $binding2), $this->manager->getBindings(array('package1', 'package2'), BindingState::UNDECIDED));
    }

    public function testGetUndecidedBindingsDoesNotIncludeRootBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::UNDECIDED));
        $this->assertSame(array(), $this->manager->getBindings('root', BindingState::UNDECIDED));
    }

    public function testGetUndecidedBindingsDoesNotIncludeHeldBackBindings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::UNDECIDED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::UNDECIDED));
    }

    public function testGetUndecidedBindingsDoesNotIncludeIgnoredBindings()
    {
        $this->initDefaultManager();

        // Duplicate type - disabled
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::UNDECIDED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::UNDECIDED));
    }

    public function testGetUndecidedBindingsDoesNotIncludeEnabledBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::UNDECIDED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::UNDECIDED));
    }

    public function testGetUndecidedBindingsDoesNotIncludeDisabledBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::UNDECIDED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::UNDECIDED));
    }

    public function testGetHeldBackBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding3 = BindingDescriptor::create('/path3', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding4 = BindingDescriptor::create('/path4', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding3->getUuid());
        $this->installInfo3->addEnabledBindingUuid($binding4->getUuid());

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
            $binding4,
        ), $this->manager->getBindings(null, BindingState::HELD_BACK));

        $this->assertSame(array($binding2), $this->manager->getBindings('package1', BindingState::HELD_BACK));
        $this->assertSame(array($binding2, $binding3), $this->manager->getBindings(array('package1', 'package2'), BindingState::HELD_BACK));
    }

    public function testGetHeldBackBindingsDoesNotIncludeIgnoredBindings()
    {
        $this->initDefaultManager();

        // Duplicate type - disabled
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::HELD_BACK));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::HELD_BACK));
    }

    public function testGetHeldBackBindingsIncludesNewBindings()
    {
        $this->initDefaultManager();

        // neither enabled nor disabled
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array($binding), $this->manager->getBindings(null, BindingState::HELD_BACK));
        $this->assertSame(array($binding), $this->manager->getBindings('package1', BindingState::HELD_BACK));
    }

    public function testGetHeldBackBindingsIncludesDisabledBindings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->assertSame(array($binding), $this->manager->getBindings(null, BindingState::HELD_BACK));
        $this->assertSame(array($binding), $this->manager->getBindings('package1', BindingState::HELD_BACK));
    }

    public function testGetIgnoredBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding3 = BindingDescriptor::create('/path3', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding4 = BindingDescriptor::create('/path4', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding3->getUuid());
        $this->installInfo3->addEnabledBindingUuid($binding4->getUuid());

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
            $binding4,
        ), $this->manager->getBindings(null, BindingState::IGNORED));

        $this->assertSame(array($binding2), $this->manager->getBindings('package1', BindingState::IGNORED));
        $this->assertSame(array($binding2, $binding3), $this->manager->getBindings(array('package1', 'package2'), BindingState::IGNORED));
    }

    public function testGetIgnoredBindingsDoesNotIncludeBindingsWithUnloadedTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = BindingDescriptor::create('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = BindingDescriptor::create('/path2', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());

        $this->assertSame(array(), $this->manager->getBindings(null, BindingState::IGNORED));
        $this->assertSame(array(), $this->manager->getBindings('package1', BindingState::IGNORED));
    }

    public function testGetIgnoredBindingsIncludesNewBindings()
    {
        $this->initDefaultManager();

        // neither enabled nor disabled
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));

        $this->assertSame(array($binding), $this->manager->getBindings(null, BindingState::IGNORED));
        $this->assertSame(array($binding), $this->manager->getBindings('package1', BindingState::IGNORED));
    }

    public function testGetIgnoredBindingsIncludesDisabledBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->assertSame(array($binding), $this->manager->getBindings(null, BindingState::IGNORED));
        $this->assertSame(array($binding), $this->manager->getBindings('package1', BindingState::IGNORED));
    }

    public function testFindBindingsWithUuid()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor(
            $uuid1 = Uuid::fromString('f966a2e1-4738-42ac-b007-1ac8798c1877'),
            '/path1',
            'my/type'
        );
        $binding2 = new BindingDescriptor(
            $uuid2 = Uuid::fromString('ecc5bb18-a4be-483d-9682-3999504b80d5'),
            '/path2',
            'my/type'
        );
        $binding3 = new BindingDescriptor(
            $uuid3 = Uuid::fromString('ecc0b0b5-67ff-4b01-9836-9aa4d5136af4'),
            '/path3',
            'my/type'
        );

        $this->rootPackageFile->addBindingDescriptor($binding1);
        $this->packageFile1->addBindingDescriptor($binding1);
        $this->packageFile1->addBindingDescriptor($binding2);
        $this->packageFile2->addBindingDescriptor($binding3);

        $this->assertSame(array($binding1), $this->manager->findBindings($uuid1));
        $this->assertSame(array($binding2), $this->manager->findBindings($uuid2));
        $this->assertSame(array($binding3), $this->manager->findBindings($uuid3));

        $this->assertSame(array($binding2), $this->manager->findBindings($uuid2, 'package1'));
        $this->assertSame(array(), $this->manager->findBindings($uuid2, 'package2'));
    }

    public function testFindBindingsWithUuidPrefix()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor(
            Uuid::fromString('f966a2e1-4738-42ac-b007-1ac8798c1877'),
            '/path1',
            'my/type'
        );
        $binding2 = new BindingDescriptor(
            Uuid::fromString('ecc5bb18-a4be-483d-9682-3999504b80d5'),
            '/path2',
            'my/type'
        );
        $binding3 = new BindingDescriptor(
            Uuid::fromString('ecc0b0b5-67ff-4b01-9836-9aa4d5136af4'),
            '/path3',
            'my/type'
        );

        $this->rootPackageFile->addBindingDescriptor($binding1);
        $this->packageFile1->addBindingDescriptor($binding2);
        $this->packageFile2->addBindingDescriptor($binding3);

        $this->assertSame(array($binding1), $this->manager->findBindings('f966a2'));
        $this->assertSame(array($binding2), $this->manager->findBindings('ecc5bb'));
        $this->assertSame(array($binding2, $binding3), $this->manager->findBindings('ecc'));

        $this->assertSame(array($binding1), $this->manager->findBindings('f966a2', 'root'));
        $this->assertSame(array(), $this->manager->findBindings('f966a2', 'package1'));
        $this->assertSame(array($binding2), $this->manager->findBindings('ecc', 'package1'));
        $this->assertSame(array($binding3), $this->manager->findBindings('ecc', 'package2'));
        $this->assertSame(array($binding2, $binding3), $this->manager->findBindings('ecc', array('package1', 'package2')));
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

    public function testBuildDiscoveryDoesNotAddBindingsForUnknownTypes()
    {
        $this->initDefaultManager();

        // The type could be defined in an optional package
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningsForBindingsWithUnknownParameters()
    {
        $this->initDefaultManager();

        // Required parameter is missing
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type', array(
            'param' => 'value',
        )));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->matchesRegularExpression('~.*"param" does not exist.*~'));

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningsForBindingsWithMissingParameters()
    {
        $this->initDefaultManager();

        // Required parameter is missing
        $this->rootPackageFile->addBindingDescriptor(BindingDescriptor::create('/path', 'my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', true),
        )));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->matchesRegularExpression('~.*"param" is missing.*~'));

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningsForDuplicatedTypes()
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

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\DiscoveryNotEmptyException
     */
    public function testBuildDiscoveryFailsIfDiscoveryContainsBindings()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->once())
            ->method('getBindings')
            ->willReturn(array($this->getMock('Puli\Discovery\Api\ResourceBinding')));

        $this->discovery->expects($this->never())
            ->method('define');

        $this->manager->buildDiscovery();
    }

    /**
     * @expectedException \Puli\RepositoryManager\Discovery\DiscoveryNotEmptyException
     */
    public function testBuildDiscoveryFailsIfDiscoveryContainsTypes()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->once())
            ->method('getTypes')
            ->willReturn(array(new BindingType('type')));

        $this->discovery->expects($this->never())
            ->method('define');

        $this->manager->buildDiscovery();
    }

    public function testClearDiscovery()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->once())
            ->method('clear');

        $this->manager->clearDiscovery();
    }

    private function initDefaultManager()
    {
        $this->rootPackageFile->addInstallInfo($this->installInfo1);
        $this->rootPackageFile->addInstallInfo($this->installInfo2);
        $this->rootPackageFile->addInstallInfo($this->installInfo3);

        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));
        $this->packages->add(new Package($this->packageFile1, $this->packageDir1, $this->installInfo1));
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2, $this->installInfo2));
        $this->packages->add(new Package($this->packageFile3, $this->packageDir3, $this->installInfo3));

        $this->manager = new DiscoveryManager($this->environment, $this->packages, $this->packageFileStorage, $this->logger);
    }
}
