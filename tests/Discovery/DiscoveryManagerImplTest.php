<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Discovery;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use Puli\Discovery\Api\Binding\BindingType;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingParameterDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeState;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Discovery\DiscoveryManagerImpl;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Tests\ManagerTestCase;
use Puli\Manager\Tests\TestException;
use Rhumsaa\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManagerImplTest extends ManagerTestCase
{
    const NOT_FOUND_UUID = 'fa1a334b-35ba-4662-ab5e-d64394f3081e';

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

    public function testLoadedBindingsContainDefaultParameters()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('optional', BindingParameterDescriptor::OPTIONAL, 'default'),
            new BindingParameterDescriptor('required', BindingParameterDescriptor::REQUIRED),
        )));

        $this->packageFile1->addBindingDescriptor(new BindingDescriptor('/path', 'my/type', array(
            'required' => 'value',
        )));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testLoadFailsIfDuplicateUuid()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);

        $this->manager->getBindings();
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

    public function testAddBindingTypeDoesNotFailIfAlreadyDefinedAndNoDuplicateCheck()
    {
        $this->initDefaultManager();

        $bindingType1 = new BindingTypeDescriptor('my/type');
        $bindingType2 = new BindingTypeDescriptor('my/type');

        $this->packageFile1->addTypeDescriptor($bindingType1);

        $this->discovery->expects($this->never())
            ->method('defineType');

        // The type is duplicated now
        $this->discovery->expects($this->once())
            ->method('undefineType')
            ->with('my/type');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType2) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingType2), $types);
            }));

        $this->manager->addBindingType($bindingType2, DiscoveryManager::NO_DUPLICATE_CHECK);

        $this->assertTrue($bindingType1->isDuplicate());
        $this->assertTrue($bindingType2->isDuplicate());
    }

    public function testAddBindingTypeAddsBindingsWithTypeNotLoaded()
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

    public function testGetBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2); // duplicate

        $this->assertSame($type1, $this->manager->getBindingType('my/type1'));
        $this->assertSame($type2, $this->manager->getBindingType('my/type2'));
        $this->assertSame($type2, $this->manager->getBindingType('my/type2', 'vendor/package1'));
        $this->assertSame($type3, $this->manager->getBindingType('my/type2', 'vendor/package2'));
    }

    /**
     * @expectedException \Puli\Discovery\Api\NoSuchTypeException
     */
    public function testGetBindingTypeFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getBindingType('my/type');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetBindingTypeFailsIfPackageNoString()
    {
        $this->initDefaultManager();

        $this->manager->getBindingType('my/type', 1234);
    }

    public function testGetBindingTypes()
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
    }

    public function testFindBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2); // duplicate
        $this->packageFile3->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type4'));

        $expr1 = Expr::same(BindingTypeDescriptor::CONTAINING_PACKAGE, 'vendor/package1');

        $expr2 = Expr::same(BindingTypeDescriptor::STATE, BindingTypeState::DUPLICATE);

        $expr3 = $expr1->andX($expr2);

        $this->assertSame(array($type2), $this->manager->findBindingTypes($expr1));
        $this->assertSame(array($type2, $type3), $this->manager->findBindingTypes($expr2));
        $this->assertSame(array($type2), $this->manager->findBindingTypes($expr3));
    }

    public function testHasBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type2'));

        $this->assertTrue($this->manager->hasBindingType('my/type1'));
        $this->assertTrue($this->manager->hasBindingType('my/type2', 'vendor/package1'));
        $this->assertFalse($this->manager->hasBindingType('my/type2', 'vendor/package2'));
        $this->assertFalse($this->manager->hasBindingType('my/type3'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testHasBindingTypeFailsIfPackageNoString()
    {
        $this->initDefaultManager();

        $this->manager->hasBindingType('my/type', 1234);
    }

    public function testHasBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile1->addTypeDescriptor($type3 = clone $type2);

        $expr1 = Expr::same(BindingTypeDescriptor::CONTAINING_PACKAGE, 'vendor/package1')
            ->andSame(BindingTypeDescriptor::STATE, BindingTypeState::ENABLED);

        $expr2 = Expr::same(BindingTypeDescriptor::CONTAINING_PACKAGE, 'vendor/package2')
            ->andSame(BindingTypeDescriptor::STATE, BindingTypeState::DUPLICATE);

        $this->assertTrue($this->manager->hasBindingTypes());
        $this->assertTrue($this->manager->hasBindingTypes($expr1));
        $this->assertFalse($this->manager->hasBindingTypes($expr2));
    }

    public function testHasNoBindingTypes()
    {
        $this->initDefaultManager();

        $this->assertFalse($this->manager->hasBindingTypes());
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
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
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

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testAddBindingFailsIfUuidDuplicatedInPackage()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor('/path', 'my/type');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);
        $this->installInfo1->addEnabledBindingUuid($binding2->getUuid());

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding($binding1);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testAddBindingFailsIfUuidDuplicatedInRoot()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor('/path', 'my/type');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding($binding1);
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

    public function testAddBindingDoesNotFailIfTypeNotDefinedAndNoTypeCheck()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type');

        // The type does not exist
        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($binding), $bindings);
                PHPUnit_Framework_Assert::assertTrue($binding->isTypeNotLoaded());
            }));

        $this->manager->addBinding($binding, DiscoveryManager::NO_TYPE_CHECK);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
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

    public function testAddBindingDoesNotFailIfTypeNotEnabledAndNoTypeCheck()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type');

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        // The type is not enabled
        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($binding), $bindings);
                PHPUnit_Framework_Assert::assertTrue($binding->isTypeNotLoaded());
            }));

        $this->manager->addBinding($binding, DiscoveryManager::NO_TYPE_CHECK);
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\MissingParameterException
     */
    public function testAddBindingFailsIfMissingParameters()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
        )));

        $this->discovery->expects($this->never())
            ->method('bind');

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
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
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

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchBindingException
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
     * @expectedException \Puli\Manager\Api\Discovery\CannotEnableBindingException
     * @expectedExceptionCode 1
     */
    public function testEnableBindingFailsIfBindingInRootPackage()
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
     * @expectedException \Puli\Manager\Api\Discovery\CannotEnableBindingException
     * @expectedExceptionCode 2
     */
    public function testEnableBindingFailsIfTypeNotFound()
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
     * @expectedException \Puli\Manager\Api\Discovery\CannotEnableBindingException
     * @expectedExceptionCode 2
     */
    public function testEnableBindingFailsIfTypeNotEnabled()
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

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchBindingException
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
     * @expectedException \Puli\Manager\Api\Discovery\CannotDisableBindingException
     * @expectedExceptionCode 1
     */
    public function testDisableBindingFailsIfBindingInRootPackage()
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
     * @expectedException \Puli\Manager\Api\Discovery\CannotDisableBindingException
     * @expectedExceptionCode 2
     */
    public function testDisableBindingFailsIfTypeNotLoaded()
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
     * @expectedException \Puli\Manager\Api\Discovery\CannotDisableBindingException
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

    public function testGetBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type'));

        $this->assertSame($binding1, $this->manager->getBinding($binding1->getUuid()));
        $this->assertSame($binding2, $this->manager->getBinding($binding2->getUuid()));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchBindingException
     */
    public function testGetBindingFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getBinding(Uuid::fromString(self::NOT_FOUND_UUID));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetBindingFailsIfPackageNoString()
    {
        $this->initDefaultManager();

        $this->manager->getBinding(Uuid::fromString(self::NOT_FOUND_UUID), 1234);
    }

    public function testGetBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding3 = new BindingDescriptor('/path3', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding2->getUuid());
        $this->installInfo3->addEnabledBindingUuid($binding3->getUuid());

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
        ), $this->manager->getBindings());
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
        $this->packageFile1->addBindingDescriptor($binding2);
        $this->packageFile2->addBindingDescriptor($binding3);

        $expr1 = Expr::startsWith(BindingDescriptor::UUID, 'ecc');

        $expr2 = $expr1->andSame(BindingDescriptor::CONTAINING_PACKAGE, 'vendor/package1');

        $expr3 = $expr1->andSame(BindingDescriptor::CONTAINING_PACKAGE, 'vendor/root');

        $this->assertSame(array($binding2, $binding3), $this->manager->findBindings($expr1));
        $this->assertSame(array($binding2), $this->manager->findBindings($expr2));
        $this->assertSame(array(), $this->manager->findBindings($expr3));
    }

    public function testHasBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type'));

        $this->assertTrue($this->manager->hasBinding($binding1->getUuid()));
        $this->assertTrue($this->manager->hasBinding($binding2->getUuid()));
        $this->assertFalse($this->manager->hasBinding(Uuid::fromString(self::NOT_FOUND_UUID)));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testHasBindingFailsIfPackageNoString()
    {
        $this->initDefaultManager();

        $this->manager->hasBinding(Uuid::fromString(self::NOT_FOUND_UUID), 1234);
    }

    public function testHasBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type1'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type2'));

        $expr1 = Expr::same(BindingDescriptor::CONTAINING_PACKAGE, 'vendor/package1');

        $expr2 = Expr::same(BindingDescriptor::STATE, BindingState::ENABLED);

        $expr3 = $expr1->andX($expr2);

        $this->assertTrue($this->manager->hasBindings());
        $this->assertTrue($this->manager->hasBindings($expr1));
        $this->assertTrue($this->manager->hasBindings($expr2));
        $this->assertFalse($this->manager->hasBindings($expr3));
    }

    public function testHasNoBindings()
    {
        $this->initDefaultManager();

        $this->assertFalse($this->manager->hasBindings());
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
            new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
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
     * @expectedException \Puli\Manager\Api\Discovery\DiscoveryNotEmptyException
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
     * @expectedException \Puli\Manager\Api\Discovery\DiscoveryNotEmptyException
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

        $this->manager = new DiscoveryManagerImpl($this->environment, $this->discovery, $this->packages, $this->packageFileStorage, $this->logger);
    }
}
