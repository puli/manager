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
use Puli\Discovery\Api\NoQueryMatchesException;
use Puli\RepositoryManager\Api\Discovery\BindingCriteria;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingTypeState;
use Puli\RepositoryManager\Api\Package\InstallInfo;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Puli\RepositoryManager\Discovery\DiscoveryManagerImpl;
use Puli\RepositoryManager\Package\PackageFileStorage;
use Puli\RepositoryManager\Tests\ManagerTestCase;
use Puli\RepositoryManager\Tests\TestException;
use Rhumsaa\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManagerImplTest extends ManagerTestCase
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
     * @var DiscoveryManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/DiscoveryManagerImplTest'.rand(10000, 99999), 0777, true)) {}

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures', $this->tempDir);

        $this->packageDir1 = $this->tempDir.'/package1';
        $this->packageDir2 = $this->tempDir.'/package2';
        $this->packageDir3 = $this->tempDir.'/package3';

        $this->packageFile1 = new PackageFile();
        $this->packageFile2 = new PackageFile();
        $this->packageFile3 = new PackageFile();

        $this->installInfo1 = new InstallInfo('vendor/package1', $this->packageDir1);
        $this->installInfo2 = new InstallInfo('vendor/package2', $this->packageDir2);
        $this->installInfo3 = new InstallInfo('vendor/package3', $this->packageDir3);

        $this->packages = new PackageCollection();

        $this->logger = $this->getMock('Psr\Log\LoggerInterface');

        $this->packageFileStorage = $this->getMockBuilder('Puli\RepositoryManager\Package\PackageFileStorage')
            ->disableOriginalConstructor()
            ->getMock();

        $this->initEnvironment($this->tempDir.'/home', $this->tempDir.'/root');
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testLoadedBindingsContainDefaultParameters()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('optional', false, 'default'),
            new BindingParameterDescriptor('required', true),
        )));

        $this->packageFile1->addBindingDescriptor(new BindingDescriptor('/path', 'my/type', array(
            'required' => 'value',
        )));

    }

    public function testAddBindingType()
    {
        $this->initDefaultManager();

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->discovery->expects($this->once())
            ->method('defineType')
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
            ->method('defineType');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBindingType($bindingType);

        $this->assertFalse($bindingType->isEnabled());
    }

    public function testAddBindingTypeAddsHeldBackBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type'));

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->discovery->expects($this->once())
            ->method('defineType')
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

        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->discovery->expects($this->once())
            ->method('defineType')
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
            ->method('defineType')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('undefineType')
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
        $this->assertFalse($bindingType->isLoaded());
    }

    public function testRemoveBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('undefineType')
            ->with('my/type');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');

        $this->assertFalse($bindingType->isLoaded());
    }

    public function testRemoveBindingTypeIgnoresNonExistingTypes()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeIgnoresTypesInInstalledPackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('undefineType');

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
            ->method('defineType')
            ->with($bindingType1->toBindingType());

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');

        $this->assertFalse($bindingType1->isLoaded());
        $this->assertTrue($bindingType2->isEnabled());
    }

    public function testRemoveBindingTypeDoesNotDefineTypeIfStillDuplicated()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType2 = clone $bindingType1);
        $this->packageFile2->addTypeDescriptor($bindingType3 = clone $bindingType1);

        $this->discovery->expects($this->never())
            ->method('defineType');

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');

        $this->assertFalse($bindingType1->isLoaded());
        $this->assertTrue($bindingType2->isDuplicate());
        $this->assertTrue($bindingType3->isDuplicate());
    }

    public function testRemoveBindingTypeUnbindsCorrespondingBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->once())
            ->method('undefineType')
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
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());

        $this->discovery->expects($this->once())
            ->method('undefineType')
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

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType2 = clone $bindingType1);
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->once())
            ->method('defineType')
            ->with($bindingType1->toBindingType());

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType1) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeDoesNotAddFormerlyIgnoredBindingsIfStillDuplicated()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType2 = clone $bindingType1);
        $this->packageFile2->addTypeDescriptor($bindingType3 = clone $bindingType1);
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('defineType');

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType1) {
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
            ->method('defineType')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->never())
            ->method('undefineType');

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
            ->method('defineType');

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testGetAllBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2); // duplicate
        $this->packageFile3->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type4'));

        $this->assertSame(array(
            $type1,
            $type2,
            $type3,
            $type4,
        ), $this->manager->getBindingTypes());

        $this->assertSame(array($type2), $this->manager->getBindingTypes('vendor/package1'));
        $this->assertSame(array($type2, $type3), $this->manager->getBindingTypes(array('vendor/package1', 'vendor/package2')));
    }

    public function testGetEnabledBindingTypesDoesNotIncludeDuplicateTypes()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2);
        $this->packageFile2->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type3'));

        $this->assertEquals(array($type1, $type4), $this->manager->getBindingTypes(null, BindingTypeState::ENABLED));
        $this->assertEquals(array($type1), $this->manager->getBindingTypes('vendor/package1', BindingTypeState::ENABLED));
        $this->assertEquals(array($type4), $this->manager->getBindingTypes('vendor/package2', BindingTypeState::ENABLED));
        $this->assertEquals(array($type1, $type4), $this->manager->getBindingTypes(array('vendor/package1', 'vendor/package2'), BindingTypeState::ENABLED));
    }

    public function testGetDuplicateBindingTypesDoesNotIncludeEnabledTypes()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2);
        $this->packageFile2->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type3'));

        $this->assertEquals(array($type2, $type3), $this->manager->getBindingTypes(null, BindingTypeState::DUPLICATE));
        $this->assertEquals(array($type2), $this->manager->getBindingTypes('vendor/package1', BindingTypeState::DUPLICATE));
        $this->assertEquals(array($type3), $this->manager->getBindingTypes('vendor/package2', BindingTypeState::DUPLICATE));
        $this->assertEquals(array($type2, $type3), $this->manager->getBindingTypes(array('vendor/package1', 'vendor/package2'), BindingTypeState::DUPLICATE));
    }

    public function testAddBinding()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($binding), $bindings);
                PHPUnit_Framework_Assert::assertTrue($binding->isEnabled());
            }));

        $this->manager->addBinding($binding);
    }

    public function testAddBindingForTypeWithDefaultParameters()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type', array(), 'xpath');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        )));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'default'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($binding), $bindings);
                PHPUnit_Framework_Assert::assertTrue($binding->isEnabled());
            }));

        $this->manager->addBinding($binding);
    }

    public function testAddBindingDoesNotAddBindingDuplicatedInPackage()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($existing = new BindingDescriptor('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($existing->getUuid());

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($binding), $bindings);
                PHPUnit_Framework_Assert::assertTrue($binding->isEnabled());
            }));

        $this->manager->addBinding($binding);

        // The package binding is marked as duplicate, the root binding is enabled
        $this->assertTrue($existing->isDuplicate());
    }

    public function testAddBindingDoesNotAddBindingDuplicatedInRoot()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding(new BindingDescriptor('/path', 'my/type'));
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

        $this->manager->addBinding(new BindingDescriptor('/path', 'my/type'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\TypeNotEnabledException
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

        $this->manager->addBinding(new BindingDescriptor('/path', 'my/type'));
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

        $this->manager->addBinding(new BindingDescriptor('/path', 'my/type'));
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

        $this->manager->addBinding(new BindingDescriptor('/path', 'my/type'));
    }

    public function testAddBindingUnbindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->rootPackageFile->addBindingDescriptor($existing = new BindingDescriptor('/existing', 'my/type'));
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
            $this->manager->addBinding($binding);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existing), $this->rootPackageFile->getBindingDescriptors());
        $this->assertFalse($binding->isLoaded());
    }

    public function testRemoveBinding()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->rootPackageFile->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));

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

        $this->manager->removeBinding($binding->getUuid());

        $this->assertFalse($binding->isLoaded());
    }

    public function testRemoveBindingWorksWithDefaultParameters()
    {
        $this->initDefaultManager();

        // default parameters: ["param" => "default"]
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        )));

        // actual parameters: []
        $this->rootPackageFile->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'default'), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindings);
            }));

        $this->manager->removeBinding($binding->getUuid());
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));
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

        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
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

        $this->manager->removeBinding($binding1->getUuid());

        $this->assertTrue($binding2->isEnabled());
        $this->assertFalse($binding1->isLoaded());
    }

    public function testRemoveHeldBackBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

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
        $this->rootPackageFile->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

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

        $binding = new BindingDescriptor('/path', 'my/type');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($existing = new BindingDescriptor('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($existing->getUuid());

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->exactly(2))
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile);

        // Duplicate, not bound
        $this->manager->addBinding($binding);

        // Duplicate removed, not unbound
        $this->manager->removeBinding($binding->getUuid());

        // Duplicate is still enabled
        $this->assertTrue($existing->isEnabled());
        $this->assertFalse($binding->isLoaded());
    }

    public function testEnableBindingBindsIfUndecided()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('vendor/package1');
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('vendor/package1');
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));
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
        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1) {
                $installInfo1 = $rootPackageFile->getInstallInfo('vendor/package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('vendor/package2');
                $enabledBindingUuids1 = $installInfo1->getEnabledBindingUuids();
                $enabledBindingUuids2 = $installInfo2->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids2);
            }));

        $this->manager->enableBinding($binding1->getUuid());

        $this->assertTrue($binding1->isEnabled());
        $this->assertTrue($binding2->isDuplicate());
    }

    public function testEnableBindingForOnePackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1) {
                $installInfo1 = $rootPackageFile->getInstallInfo('vendor/package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('vendor/package2');
                $enabledBindingUuids1 = $installInfo1->getEnabledBindingUuids();
                $enabledBindingUuids2 = $installInfo2->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array(), $enabledBindingUuids2);
            }));

        $this->manager->enableBinding($binding1->getUuid(), 'vendor/package1');

        $this->assertTrue($binding1->isEnabled());
        $this->assertFalse($binding2->isEnabled());
    }

    public function testEnableBindingForMultiplePackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile2->addBindingDescriptor($binding2 = clone $binding1);
        $this->packageFile3->addBindingDescriptor($binding3 = clone $binding1);

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding1, $binding2) {
                $installInfo1 = $rootPackageFile->getInstallInfo('vendor/package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('vendor/package2');
                $installInfo3 = $rootPackageFile->getInstallInfo('vendor/package3');
                $enabledBindingUuids1 = $installInfo1->getEnabledBindingUuids();
                $enabledBindingUuids2 = $installInfo2->getEnabledBindingUuids();
                $enabledBindingUuids3 = $installInfo3->getEnabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $enabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array($binding2->getUuid()), $enabledBindingUuids2);
                PHPUnit_Framework_Assert::assertSame(array(), $enabledBindingUuids3);
            }));

        $this->manager->enableBinding($binding1->getUuid(), array('vendor/package1', 'vendor/package2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\NoSuchBindingException
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
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingInRootPackageOnly()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingInRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding1->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingHeldBack()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->enableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotEnableBindingException
     */
    public function testEnableBindingFailsIfBindingIgnored()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

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
        $this->packageFile1->addBindingDescriptor($existing = new BindingDescriptor('/existing', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addEnabledBindingUuid($existing->getUuid());
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('vendor/package1');
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $installInfo = $rootPackageFile->getInstallInfo('vendor/package1');
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));
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
        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
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
                $installInfo1 = $rootPackageFile->getInstallInfo('vendor/package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('vendor/package2');
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
        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
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
                $installInfo1 = $rootPackageFile->getInstallInfo('vendor/package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('vendor/package2');
                $disabledBindingUuids1 = $installInfo1->getDisabledBindingUuids();
                $disabledBindingUuids2 = $installInfo2->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $disabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array(), $disabledBindingUuids2);
            }));

        $this->manager->disableBinding($binding1->getUuid(), 'vendor/package1');

        $this->assertTrue($binding1->isDisabled());
        $this->assertFalse($binding2->isDisabled());
    }

    public function testDisableBindingForMultiplePackagesDoesNotUnbind()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
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
                $installInfo1 = $rootPackageFile->getInstallInfo('vendor/package1');
                $installInfo2 = $rootPackageFile->getInstallInfo('vendor/package2');
                $installInfo3 = $rootPackageFile->getInstallInfo('vendor/package3');
                $disabledBindingUuids1 = $installInfo1->getDisabledBindingUuids();
                $disabledBindingUuids2 = $installInfo2->getDisabledBindingUuids();
                $disabledBindingUuids3 = $installInfo3->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding1->getUuid()), $disabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array($binding2->getUuid()), $disabledBindingUuids2);
                PHPUnit_Framework_Assert::assertSame(array(), $disabledBindingUuids3);
            }));

        $this->manager->disableBinding($binding1->getUuid(), array('vendor/package1', 'vendor/package2'));
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\NoSuchBindingException
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
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingInRootPackageOnly()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingInRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding1->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingHeldBack()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->disableBinding($binding->getUuid());
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\CannotDisableBindingException
     */
    public function testDisableBindingFailsIfBindingIgnored()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

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
        $this->packageFile1->addBindingDescriptor($existing = new BindingDescriptor('/existing', 'my/type', array('param' => 'value'), 'xpath'));
        $this->installInfo1->addDisabledBindingUuid($existing->getUuid());
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
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
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));
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

    public function testGetAllBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding3 = new BindingDescriptor('/path3', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding4 = new BindingDescriptor('/path4', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding2->getUuid());
        $this->installInfo3->addEnabledBindingUuid($binding4->getUuid());

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
            $binding4,
        ), $this->manager->getBindings());
    }

    public function testGetAllBindingsMergesDuplicates()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());

        $this->assertCount(1, $this->manager->getBindings());
    }

    public function testFindBindings()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor(
            '/path1',
            'my/type',
            array(),
            'glob',
            $uuid1 = Uuid::fromString('f966a2e1-4738-42ac-b007-1ac8798c1877')
        );
        $binding2 = new BindingDescriptor(
            '/path2',
            'my/type',
            array(),
            'glob',
            $uuid2 = Uuid::fromString('ecc5bb18-a4be-483d-9682-3999504b80d5')
        );
        $binding3 = new BindingDescriptor(
            '/path3',
            'my/type',
            array(),
            'glob',
            $uuid3 = Uuid::fromString('ecc0b0b5-67ff-4b01-9836-9aa4d5136af4')
        );

        $this->rootPackageFile->addBindingDescriptor($binding1);
        $this->packageFile1->addBindingDescriptor($binding1);
        $this->packageFile1->addBindingDescriptor($binding2);
        $this->packageFile2->addBindingDescriptor($binding3);

        $criteria1 = BindingCriteria::create()
            ->setUuidPrefix('ecc');

        $criteria2 = BindingCriteria::create()
            ->setUuidPrefix('ecc')
            ->addPackageName('vendor/package1');

        $criteria3 = BindingCriteria::create()
            ->setUuidPrefix('ecc')
            ->addPackageName('vendor/root');

        $this->assertSame(array($binding2, $binding3), $this->manager->findBindings($criteria1));
        $this->assertSame(array($binding2), $this->manager->findBindings($criteria2));
        $this->assertSame(array(), $this->manager->findBindings($criteria3));
    }

    public function testBuildDiscovery()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('defineType')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array(), 'glob');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryOnlyIncludesEnabledBindingsOfInstalledPackages()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding3 = new BindingDescriptor('/path3', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());
        $this->installInfo1->addDisabledBindingUuid($binding3->getUuid());
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('defineType')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path2', 'my/type', array(), 'glob');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryAddsDuplicateBindingsOnlyOnce()
    {
        $this->initDefaultManager();

        $this->packageFile1->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile2->addBindingDescriptor($binding2 = new BindingDescriptor('/path', 'my/type'));
        $this->installInfo1->addEnabledBindingUuid($binding1->getUuid());
        $this->installInfo2->addEnabledBindingUuid($binding2->getUuid());
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('defineType')
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
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningsForBindingsWithUnknownParameters()
    {
        $this->initDefaultManager();

        // Required parameter is missing
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type', array(
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
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path', 'my/type'));
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
            ->method('defineType');

        $this->logger->expects($this->once())
            ->method('warning');

        $this->manager->buildDiscovery();
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\DiscoveryNotEmptyException
     */
    public function testBuildDiscoveryFailsIfDiscoveryContainsBindings()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->once())
            ->method('getBindings')
            ->willReturn(array($this->getMock('Puli\Discovery\Api\ResourceBinding')));

        $this->discovery->expects($this->never())
            ->method('defineType');

        $this->manager->buildDiscovery();
    }

    /**
     * @expectedException \Puli\RepositoryManager\Api\Discovery\DiscoveryNotEmptyException
     */
    public function testBuildDiscoveryFailsIfDiscoveryContainsTypes()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->once())
            ->method('getDefinedTypes')
            ->willReturn(array(new BindingType('type')));

        $this->discovery->expects($this->never())
            ->method('defineType');

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

        $this->manager = new DiscoveryManagerImpl($this->environment, $this->packages, $this->packageFileStorage, $this->logger);
    }
}
