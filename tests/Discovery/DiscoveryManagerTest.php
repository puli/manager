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

use Exception;
use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use Puli\Discovery\Api\BindingException;
use Puli\Discovery\Api\BindingType;
use Puli\Discovery\Api\DuplicateTypeException;
use Puli\Discovery\Api\MissingParameterException;
use Puli\Discovery\Api\NoSuchTypeException;
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Discovery\BindingState;
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
    }

    /**
     * @expectedException \Puli\Discovery\Api\DuplicateTypeException
     */
    public function testAddBindingTypeFailsIfAlreadyDefined()
    {
        $this->initDefaultManager();

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->packageFile1->addTypeDescriptor($bindingType);

        $this->discovery->expects($this->once())
            ->method('define')
            ->willThrowException(new DuplicateTypeException());

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBindingType($bindingType);
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

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Some exception
     */
    public function testAddBindingTypeUndefinesTypeIfSavingFails()
    {
        $this->initDefaultManager();

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
            ->willThrowException(new Exception('Some exception'));

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

    public function testRemoveBindingTypeDefinesTypeIfResolvingDuplication()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType);

        $this->discovery->expects($this->once())
            ->method('define')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
    }

    public function testRemoveBindingTypeDoesNotDefineTypeIfStillDuplicated()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor($bindingType);
        $this->packageFile2->addTypeDescriptor($bindingType);

        $this->discovery->expects($this->never())
            ->method('define');

        $this->discovery->expects($this->never())
            ->method('undefine');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType) {
                $types = $rootPackageFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $types);
            }));

        $this->manager->removeBindingType('my/type');
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

    public function testGetBindingTypesReturnsDuplicatedTypesOnlyOnce()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($type = new BindingTypeDescriptor('my/type'));
        $this->packageFile2->addTypeDescriptor($type);

        $this->assertSame(array($type), $this->manager->getBindingTypes());
        $this->assertSame(array($type), $this->manager->getBindingTypes('package1'));
        $this->assertSame(array($type), $this->manager->getBindingTypes('package2'));
        $this->assertSame(array($type), $this->manager->getBindingTypes(array('package1', 'package2')));
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

        $this->discovery->expects($this->once())
            ->method('bind')
            ->willThrowException(new NoSuchTypeException());

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding('/path', 'my/type', array('param' => 'value'), 'xpath');
    }

    /**
     * @expectedException \Puli\Discovery\Api\MissingParameterException
     */
    public function testAddBindingFailsIfMissingParameters()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', true),
        )));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->willThrowException(new MissingParameterException());

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding('/path', 'my/type');
    }

    /**
     * @expectedException \Puli\Discovery\Api\BindingException
     */
    public function testAddBindingFailsIfNoResourcesFound()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->willThrowException(new BindingException());

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding('/path', 'my/type');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Some exception
     */
    public function testAddBindingUnbindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->once())
            ->method('unbind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new Exception('Some exception'));

        $this->manager->addBinding('/path', 'my/type', array('param' => 'value'), 'xpath');
    }

    public function testRemoveBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

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

        $this->packageFile1->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeBinding($binding->getUuid());
    }

    public function testRemoveBindingDoesNotUnbindDuplicatedBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));
        $this->packageFile1->addBindingDescriptor($binding);
        $this->installInfo1->addEnabledBindingUuid($binding->getUuid());
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

        $this->manager->removeBinding($binding->getUuid());
    }

    public function testRemoveBindingWorksIfTypeNotDefined()
    {
        $this->initDefaultManager();

        // The type is not defined, thus the binding is not loaded. Removing
        // still works, however
        $this->rootPackageFile->addBindingDescriptor($binding = BindingDescriptor::create('/path', 'my/type', array('param' => 'value'), 'xpath'));

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

    public function testFindBindings()
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
            ->method('define');

        $this->discovery->expects($this->never())
            ->method('bind');

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
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));
        $this->packages->add(new Package($this->packageFile1, $this->packageDir1, $this->installInfo1));
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2, $this->installInfo2));
        $this->packages->add(new Package($this->packageFile3, $this->packageDir3, $this->installInfo3));

        $this->manager = new DiscoveryManager($this->environment, $this->packages, $this->packageFileStorage, $this->logger);
    }
}
