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
use Webmozart\Glob\Test\TestUtil;

/**
 * @since  1.0
 *
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
        $this->tempDir = TestUtil::makeTempDir('puli-manager', __CLASS__);

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

    public function testAddRootBindingType()
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

        $this->manager->addRootBindingType($bindingType);

        $this->assertTrue($bindingType->isEnabled());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateTypeException
     */
    public function testAddRootBindingTypeFailsIfAlreadyDefined()
    {
        $this->initDefaultManager();

        $bindingType = new BindingTypeDescriptor('my/type');

        $this->packageFile1->addTypeDescriptor($bindingType);

        $this->discovery->expects($this->never())
            ->method('defineType');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBindingType($bindingType);

        $this->assertFalse($bindingType->isEnabled());
    }

    public function testAddRootBindingTypeDoesNotFailIfAlreadyDefinedAndNoDuplicateCheck()
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

        $this->manager->addRootBindingType($bindingType2, DiscoveryManager::OVERRIDE);

        $this->assertTrue($bindingType1->isDuplicate());
        $this->assertTrue($bindingType2->isDuplicate());
    }

    public function testAddRootBindingTypeAddsBindingsWithTypeNotLoaded()
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

        $this->manager->addRootBindingType($bindingType);
    }

    public function testAddRootBindingTypeUndefinesTypeIfSavingFails()
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
            $this->manager->addRootBindingType($bindingType);
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existingType), $this->rootPackageFile->getTypeDescriptors());

        $this->assertTrue($existingType->isEnabled());
        $this->assertFalse($bindingType->isLoaded());
    }

    public function testRemoveRootBindingType()
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

        $this->manager->removeRootBindingType('my/type');

        $this->assertFalse($bindingType->isLoaded());
    }

    public function testRemoveRootBindingTypeIgnoresNonExistingTypes()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeRootBindingType('my/type');
    }

    public function testRemoveRootBindingTypeIgnoresIfNotInRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('undefineType');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeRootBindingType('my/type');

        $this->assertTrue($bindingType->isEnabled());
    }

    public function testRemoveRootBindingTypeDefinesTypeIfResolvingDuplication()
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

        $this->manager->removeRootBindingType('my/type');

        $this->assertFalse($bindingType1->isLoaded());
        $this->assertTrue($bindingType2->isEnabled());
    }

    public function testRemoveRootBindingTypeDoesNotDefineTypeIfStillDuplicated()
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

        $this->manager->removeRootBindingType('my/type');

        $this->assertFalse($bindingType1->isLoaded());
        $this->assertTrue($bindingType2->isDuplicate());
        $this->assertTrue($bindingType3->isDuplicate());
    }

    public function testRemoveRootBindingTypeUnbindsCorrespondingBindings()
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

        $this->manager->removeRootBindingType('my/type');
    }

    public function testRemoveRootBindingTypeAddsFormerlyIgnoredBindings()
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

        $this->manager->removeRootBindingType('my/type');
    }

    public function testRemoveRootBindingTypeDoesNotAddFormerlyIgnoredBindingsIfStillDuplicated()
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

        $this->manager->removeRootBindingType('my/type');
    }

    public function testRemoveRootBindingTypeDoesNotEmitWarningForRemovedDuplicateType()
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

        $this->manager->removeRootBindingType('my/type');
    }

    public function testRemoveRootBindingTypeEmitsWarningIfDuplicatedMoreThanOnce()
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

        $this->manager->removeRootBindingType('my/type');
    }

    public function testRemoveRootBindingTypeDefinesTypeIfSavingFails()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('undefineType')
            ->with('my/type');

        $this->discovery->expects($this->once())
            ->method('defineType')
            ->with($bindingType->toBindingType());

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeRootBindingType('my/type');
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($bindingType), $this->rootPackageFile->getTypeDescriptors());

        $this->assertTrue($bindingType->isEnabled());
    }

    public function testRemoveRootBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addTypeDescriptor($bindingType2 = new BindingTypeDescriptor('my/type2'));
        $this->rootPackageFile->addTypeDescriptor($bindingType3 = new BindingTypeDescriptor('other/type'));

        $this->discovery->expects($this->at(0))
            ->method('undefineType')
            ->with('my/type1');

        $this->discovery->expects($this->at(1))
            ->method('undefineType')
            ->with('my/type2');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($bindingType3) {
                PHPUnit_Framework_Assert::assertSame(array($bindingType3), $rootPackageFile->getTypeDescriptors());
            }));

        $this->manager->removeRootBindingTypes(Expr::startsWith('my', BindingTypeDescriptor::NAME));

        $this->assertFalse($bindingType1->isLoaded());
        $this->assertFalse($bindingType2->isLoaded());
        $this->assertTrue($bindingType3->isLoaded());
    }

    public function testRemoveRootBindingTypesUnbindsCorrespondingBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type2'));
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path1', 'my/type1'));
        $this->rootPackageFile->addBindingDescriptor(new BindingDescriptor('/path2', 'my/type2'));

        $this->discovery->expects($this->at(0))
            ->method('undefineType')
            ->with('my/type1');

        $this->discovery->expects($this->at(1))
            ->method('undefineType')
            ->with('my/type2');

        $this->discovery->expects($this->at(2))
            ->method('unbind')
            ->with('/path1', 'my/type1', array(), 'glob');

        $this->discovery->expects($this->at(3))
            ->method('unbind')
            ->with('/path2', 'my/type2', array(), 'glob');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasTypeDescriptors());
            }));

        $this->manager->removeRootBindingTypes(Expr::startsWith('my', BindingTypeDescriptor::NAME));
    }

    public function testClearRootBindingTypesDefinesTypesIfSavingFails()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addTypeDescriptor($bindingType2 = new BindingTypeDescriptor('my/type2'));

        $this->discovery->expects($this->at(0))
            ->method('undefineType')
            ->with('my/type1');

        $this->discovery->expects($this->at(1))
            ->method('undefineType')
            ->with('my/type2');

        $this->discovery->expects($this->at(2))
            ->method('defineType')
            ->with($bindingType2->toBindingType());

        $this->discovery->expects($this->at(3))
            ->method('defineType')
            ->with($bindingType1->toBindingType());

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeRootBindingTypes(Expr::startsWith('my', BindingTypeDescriptor::NAME));
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame($bindingType1, $this->rootPackageFile->getTypeDescriptor('my/type1'));
        $this->assertSame($bindingType2, $this->rootPackageFile->getTypeDescriptor('my/type2'));
        $this->assertCount(2, $this->rootPackageFile->getTypeDescriptors());

        $this->assertTrue($bindingType1->isEnabled());
        $this->assertTrue($bindingType2->isEnabled());
    }

    public function testClearRootBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($bindingType1 = new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addTypeDescriptor($bindingType2 = new BindingTypeDescriptor('my/type2'));

        $this->discovery->expects($this->at(0))
            ->method('undefineType')
            ->with('my/type1');

        $this->discovery->expects($this->at(1))
            ->method('undefineType')
            ->with('my/type2');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasTypeDescriptors());
            }));

        $this->manager->clearRootBindingTypes();

        $this->assertFalse($bindingType1->isLoaded());
        $this->assertFalse($bindingType2->isLoaded());
    }

    public function testGetRootBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type = new BindingTypeDescriptor('my/type'));

        $this->assertSame($type, $this->manager->getRootBindingType('my/type'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testGetBindingTypeFailsIfNotFoundInRoot()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor($type = new BindingTypeDescriptor('my/type'));

        $this->manager->getRootBindingType('my/type');
    }

    public function testGetRootBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = new BindingTypeDescriptor('my/type3'));

        $this->assertSame(array(
            $type1,
            $type2,
        ), $this->manager->getRootBindingTypes());
    }

    public function testFindRootBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile1->addTypeDescriptor($type3 = clone $type2); // duplicate
        $this->packageFile2->addTypeDescriptor($type4 = new BindingTypeDescriptor('my/type4'));

        $expr1 = Expr::startsWith('my/type', BindingTypeDescriptor::NAME);

        $expr2 = Expr::same(BindingTypeState::DUPLICATE, BindingTypeDescriptor::STATE);

        $expr3 = $expr1->andX($expr2);

        $this->assertSame(array($type1, $type2), $this->manager->findRootBindingTypes($expr1));
        $this->assertSame(array($type2), $this->manager->findRootBindingTypes($expr2));
        $this->assertSame(array($type2), $this->manager->findRootBindingTypes($expr3));
    }

    public function testHasRootBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type2'));

        $this->assertTrue($this->manager->hasRootBindingType('my/type1'));
        $this->assertFalse($this->manager->hasRootBindingType('my/type2'));
        $this->assertFalse($this->manager->hasRootBindingType('my/type3'));
    }

    public function testHasRootBindingTypes()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));

        $expr1 = Expr::same(BindingTypeState::ENABLED, BindingTypeDescriptor::STATE);

        $expr2 = Expr::same(BindingTypeState::DUPLICATE, BindingTypeDescriptor::STATE);

        $this->assertTrue($this->manager->hasRootBindingTypes());
        $this->assertTrue($this->manager->hasRootBindingTypes($expr1));
        $this->assertFalse($this->manager->hasRootBindingTypes($expr2));
    }

    public function testGetBindingType()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor($type1 = new BindingTypeDescriptor('my/type1'));
        $this->packageFile1->addTypeDescriptor($type2 = new BindingTypeDescriptor('my/type2'));
        $this->packageFile2->addTypeDescriptor($type3 = clone $type2); // duplicate

        $this->assertSame($type1, $this->manager->getBindingType('my/type1', 'vendor/root'));
        $this->assertSame($type2, $this->manager->getBindingType('my/type2', 'vendor/package1'));
        $this->assertSame($type3, $this->manager->getBindingType('my/type2', 'vendor/package2'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testGetBindingTypeFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getBindingType('my/type', 'vendor/root');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetBindingTypeFailsIfTypeNoString()
    {
        $this->initDefaultManager();

        $this->manager->getBindingType(1234, 'vendor/root');
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

        $expr1 = Expr::same('vendor/package1', BindingTypeDescriptor::CONTAINING_PACKAGE);

        $expr2 = Expr::same(BindingTypeState::DUPLICATE, BindingTypeDescriptor::STATE);

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

        $expr1 = Expr::same('vendor/package1', BindingTypeDescriptor::CONTAINING_PACKAGE)
            ->andSame(BindingTypeState::ENABLED, BindingTypeDescriptor::STATE);

        $expr2 = Expr::same('vendor/package2', BindingTypeDescriptor::CONTAINING_PACKAGE)
            ->andSame(BindingTypeState::DUPLICATE, BindingTypeDescriptor::STATE);

        $this->assertTrue($this->manager->hasBindingTypes());
        $this->assertTrue($this->manager->hasBindingTypes($expr1));
        $this->assertFalse($this->manager->hasBindingTypes($expr2));
    }

    public function testHasNoBindingTypes()
    {
        $this->initDefaultManager();

        $this->assertFalse($this->manager->hasBindingTypes());
    }

    public function testAddRootBinding()
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

        $this->manager->addRootBinding($binding);
    }

    public function testAddRootBindingForTypeWithDefaultParameters()
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

        $this->manager->addRootBinding($binding);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testAddRootBindingFailsIfUuidDuplicatedInPackage()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor('/path', 'my/type');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBinding($binding1);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testAddRootBindingFailsIfUuidDuplicatedInRoot()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor('/path', 'my/type');

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding2 = clone $binding1);

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBinding($binding1);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testAddRootBindingFailsIfTypeNotDefined()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBinding(new BindingDescriptor('/path', 'my/type'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testAddRootBindingFailsIfTypeNotDefinedAndIgnoreNotEnabled()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBinding(new BindingDescriptor('/path', 'my/type'), DiscoveryManager::IGNORE_TYPE_NOT_ENABLED);
    }

    public function testAddRootBindingDoesNotFailIfTypeNotDefinedAndIgnoreTypeNotFound()
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
                PHPUnit_Framework_Assert::assertTrue($binding->isTypeNotFound());
            }));

        $this->manager->addRootBinding($binding, DiscoveryManager::IGNORE_TYPE_NOT_FOUND);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
     */
    public function testAddRootBindingFailsIfTypeNotEnabled()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBinding(new BindingDescriptor('/path', 'my/type'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
     */
    public function testAddRootBindingFailsIfTypeNotEnabledAndIgnoreTypeNotFOund()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBinding(new BindingDescriptor('/path', 'my/type'), DiscoveryManager::IGNORE_TYPE_NOT_FOUND);
    }

    public function testAddRootBindingDoesNotFailIfTypeNotEnabledAndIgnoreTypeNotEnabled()
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
                PHPUnit_Framework_Assert::assertTrue($binding->isTypeNotEnabled());
            }));

        $this->manager->addRootBinding($binding, DiscoveryManager::IGNORE_TYPE_NOT_ENABLED);
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\MissingParameterException
     */
    public function testAddRootBindingFailsIfMissingParameters()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
        )));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addRootBinding(new BindingDescriptor('/path', 'my/type'));
    }

    public function testAddRootBindingUnbindsIfSavingFailed()
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
            $this->manager->addRootBinding($binding);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existing), $this->rootPackageFile->getBindingDescriptors());
        $this->assertFalse($binding->isLoaded());
    }

    public function testRemoveRootBinding()
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

        $this->manager->removeRootBinding($binding->getUuid());

        $this->assertFalse($binding->isLoaded());
    }

    public function testRemoveRootBindingWorksWithDefaultParameters()
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

        $this->manager->removeRootBinding($binding->getUuid());
    }

    public function testRemoveRootBindingIgnoresNonExistingBindings()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeRootBinding(Uuid::uuid4());
    }

    public function testRemoveRootBindingIgnoresIfBindingNotInRootPackage()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

        $this->discovery->expects($this->never())
            ->method('unbind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->removeRootBinding($binding->getUuid());

        $this->assertTrue($binding->isEnabled());
    }

    public function testRemoveRootBindingWithTypeNotFound()
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

        $this->manager->removeRootBinding($binding->getUuid());
    }

    public function testRemoveRootBindingWithTypeNotEnabled()
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

        $this->manager->removeRootBinding($binding->getUuid());
    }

    public function testRemoveRootBindingBindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->rootPackageFile->addBindingDescriptor($binding);
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));

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
            $this->manager->removeRootBinding($binding->getUuid());
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array($binding), $this->rootPackageFile->getBindingDescriptors());
        $this->assertTrue($binding->isLoaded());
    }

    public function testRemoveRootBindings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type', array('param' => 'value1'), 'xpath'));
        $this->rootPackageFile->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type', array('param' => 'value2'), 'xpath'));
        $this->rootPackageFile->addBindingDescriptor($binding3 = new BindingDescriptor('/other3', 'my/type'));

        $this->discovery->expects($this->at(0))
            ->method('unbind')
            ->with('/path1', 'my/type', array('param' => 'value1'), 'xpath');

        $this->discovery->expects($this->at(1))
            ->method('unbind')
            ->with('/path2', 'my/type', array('param' => 'value2'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding3) {
                PHPUnit_Framework_Assert::assertSame(array($binding3), $rootPackageFile->getBindingDescriptors());
            }));

        $this->manager->removeRootBindings(Expr::startsWith('/path', BindingDescriptor::QUERY));

        $this->assertFalse($binding1->isLoaded());
        $this->assertFalse($binding2->isLoaded());
        $this->assertTrue($binding3->isLoaded());
    }

    public function testRemoveRootBindingsBindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $binding1 = new BindingDescriptor('/path1', 'my/type', array('param' => 'value1'), 'xpath');
        $binding2 = new BindingDescriptor('/path2', 'my/type', array('param' => 'value2'), 'xpath');

        $this->rootPackageFile->addBindingDescriptor($binding1);
        $this->rootPackageFile->addBindingDescriptor($binding2);
        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));

        $this->discovery->expects($this->at(0))
            ->method('unbind')
            ->with('/path1', 'my/type', array('param' => 'value1'), 'xpath');

        $this->discovery->expects($this->at(1))
            ->method('unbind')
            ->with('/path2', 'my/type', array('param' => 'value2'), 'xpath');

        $this->discovery->expects($this->at(2))
            ->method('bind')
            ->with('/path2', 'my/type', array('param' => 'value2'), 'xpath');

        $this->discovery->expects($this->at(3))
            ->method('bind')
            ->with('/path1', 'my/type', array('param' => 'value1'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeRootBindings(Expr::startsWith('/path', BindingDescriptor::QUERY));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame($binding1, $this->rootPackageFile->getBindingDescriptor($binding1->getUuid()));
        $this->assertSame($binding2, $this->rootPackageFile->getBindingDescriptor($binding2->getUuid()));
        $this->assertCount(2, $this->rootPackageFile->getBindingDescriptors());
        $this->assertTrue($binding1->isLoaded());
        $this->assertTrue($binding2->isLoaded());
    }

    public function testClearRootBindings()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type', array('param' => 'value1'), 'xpath'));
        $this->rootPackageFile->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type', array('param' => 'value2'), 'xpath'));

        $this->discovery->expects($this->at(0))
            ->method('unbind')
            ->with('/path1', 'my/type', array('param' => 'value1'), 'xpath');

        $this->discovery->expects($this->at(1))
            ->method('unbind')
            ->with('/path2', 'my/type', array('param' => 'value2'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) {
                PHPUnit_Framework_Assert::assertFalse($rootPackageFile->hasBindingDescriptors());
            }));

        $this->manager->clearRootBindings();

        $this->assertFalse($binding1->isLoaded());
        $this->assertFalse($binding2->isLoaded());
    }

    public function testHasRootBinding()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type'));

        $this->assertTrue($this->manager->hasRootBinding($binding1->getUuid()));
        $this->assertFalse($this->manager->hasRootBinding($binding2->getUuid()));
        $this->assertFalse($this->manager->hasRootBinding(Uuid::fromString(self::NOT_FOUND_UUID)));
    }

    public function testFindRootBindings()
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
        $this->rootPackageFile->addBindingDescriptor($binding2);
        $this->packageFile1->addBindingDescriptor($binding3);

        $expr1 = Expr::startsWith('ecc', BindingDescriptor::UUID);

        $expr2 = Expr::same('my/type', BindingDescriptor::TYPE_NAME);

        $expr3 = $expr1->andX($expr2);

        $this->assertSame(array($binding2), $this->manager->findRootBindings($expr1));
        $this->assertSame(array($binding1, $binding2), $this->manager->findRootBindings($expr2));
        $this->assertSame(array($binding2), $this->manager->findRootBindings($expr3));
    }

    public function testHasRootBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type1'));
        $this->rootPackageFile->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type1'));

        $expr1 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE);

        $expr2 = Expr::same(BindingState::TYPE_NOT_FOUND, BindingDescriptor::STATE);

        $this->assertTrue($this->manager->hasRootBindings());
        $this->assertTrue($this->manager->hasRootBindings($expr1));
        $this->assertFalse($this->manager->hasRootBindings($expr2));
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
                $disabledBindingUuids = $installInfo->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array(), $disabledBindingUuids);
            }));

        $this->manager->enableBinding($binding->getUuid());

        $this->assertTrue($binding->isEnabled());
    }

    public function testEnableBindingDoesNothingIfAlreadyEnabled()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type'));

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
     * @expectedException \Puli\Manager\Api\NonRootPackageExpectedException
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
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
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
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
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

        $this->assertSame(array($binding->getUuid()), $this->installInfo1->getDisabledBindingUuids());
    }

    public function testDisableBindingUnbindsIfEnabled()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));

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
     * @expectedException \Puli\Manager\Api\NonRootPackageExpectedException
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
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testDisableBindingFailsIfTypeNotFound()
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
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
     */
    public function testDisableBindingFailsIfTypeNotEnabled()
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
    }

    public function testDisableBindingRestoresEnabledBindingsIfSavingFails()
    {
        $this->initDefaultManager();

        $this->packageFile1->addTypeDescriptor(new BindingTypeDescriptor('my/type', null, array(
            new BindingParameterDescriptor('param'),
        )));
        $this->packageFile1->addBindingDescriptor($binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath'));

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

    public function testGetBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type'));
        $this->packageFile3->addBindingDescriptor($binding3 = new BindingDescriptor('/path3', 'my/type'));
        $this->installInfo1->addDisabledBindingUuid($binding2->getUuid());

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

        $expr1 = Expr::startsWith('ecc', BindingDescriptor::UUID);

        $expr2 = $expr1->andSame('vendor/package1', BindingDescriptor::CONTAINING_PACKAGE);

        $expr3 = $expr1->andSame('vendor/root', BindingDescriptor::CONTAINING_PACKAGE);

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

    public function testHasBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addTypeDescriptor(new BindingTypeDescriptor('my/type1'));
        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type1'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type2'));

        $expr1 = Expr::same('vendor/package1', BindingDescriptor::CONTAINING_PACKAGE);

        $expr2 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE);

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
        $this->installInfo1->addDisabledBindingUuid($binding2->getUuid());
        $this->packageFile1->addTypeDescriptor($bindingType = new BindingTypeDescriptor('my/type'));

        $this->discovery->expects($this->once())
            ->method('defineType')
            ->with($bindingType->toBindingType());

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path1', 'my/type', array(), 'glob');

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
