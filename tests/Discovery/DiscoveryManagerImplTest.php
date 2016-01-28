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
use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleCollection;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Discovery\DiscoveryManagerImpl;
use Puli\Manager\Module\ModuleFileStorage;
use Puli\Manager\Tests\Discovery\Fixtures\Bar;
use Puli\Manager\Tests\Discovery\Fixtures\Baz;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;
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
     * @var ModuleCollection
     */
    private $modules;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ModuleFileStorage
     */
    private $moduleFileStorage;

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

        $this->moduleDir1 = $this->tempDir.'/module1';
        $this->moduleDir2 = $this->tempDir.'/module2';
        $this->moduleDir3 = $this->tempDir.'/module3';

        $this->moduleFile1 = new ModuleFile();
        $this->moduleFile2 = new ModuleFile();
        $this->moduleFile3 = new ModuleFile();

        $this->installInfo1 = new InstallInfo('vendor/module1', $this->moduleDir1);
        $this->installInfo2 = new InstallInfo('vendor/module2', $this->moduleDir2);
        $this->installInfo3 = new InstallInfo('vendor/module3', $this->moduleDir3);

        $this->modules = new ModuleCollection();

        $this->logger = $this->getMock('Psr\Log\LoggerInterface');

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

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testLoadFailsIfDuplicateUuid()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);

        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->manager->getBindingDescriptors();
    }

    public function testLoadIgnoresModulesWithoutModuleFile()
    {
        $this->rootModuleFile->addInstallInfo($this->installInfo1);

        $this->modules->add(new RootModule($this->rootModuleFile, $this->rootDir));
        $this->modules->add(new Module(null, $this->moduleDir1, $this->installInfo1));

        $this->manager = new DiscoveryManagerImpl($this->context, $this->discovery, $this->modules, $this->moduleFileStorage, $this->logger);

        $this->assertEmpty($this->manager->getBindingDescriptors());
        $this->assertEmpty($this->manager->getTypeDescriptors());
    }

    public function testAddRootTypeDescriptor()
    {
        $this->initDefaultManager();

        $typeDescriptor = new BindingTypeDescriptor(new BindingType(Foo::clazz));

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($typeDescriptor->getType());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($typeDescriptor) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($typeDescriptor), $typeDescriptors);
            }));

        $this->manager->addRootTypeDescriptor($typeDescriptor);

        $this->assertTrue($typeDescriptor->isEnabled());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateTypeException
     */
    public function testAddRootTypeDescriptorFailsIfAlreadyDefined()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->moduleFile1->addTypeDescriptor($typeDescriptor = new BindingTypeDescriptor($type));

        $this->discovery->expects($this->never())
            ->method('addBindingType');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootTypeDescriptor($typeDescriptor);

        $this->assertFalse($typeDescriptor->isEnabled());
    }

    public function testAddRootTypeDescriptorDoesNotFailIfAlreadyDefinedAndNoDuplicateCheck()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);
        $typeDescriptor1 = new BindingTypeDescriptor($type);
        $typeDescriptor2 = clone $typeDescriptor1;

        $this->moduleFile1->addTypeDescriptor($typeDescriptor1);

        $this->discovery->expects($this->never())
            ->method('addBindingType');

        // The type is duplicated now
        $this->discovery->expects($this->once())
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($typeDescriptor2) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($typeDescriptor2), $typeDescriptors);
            }));

        $this->manager->addRootTypeDescriptor($typeDescriptor2, DiscoveryManager::OVERRIDE);

        $this->assertTrue($typeDescriptor1->isDuplicate());
        $this->assertTrue($typeDescriptor2->isDuplicate());
    }

    public function testAddRootTypeDescriptorAddsBindingsWithTypeNotLoaded()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);
        $binding = new ResourceBinding('/path', Foo::clazz);

        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $typeDescriptor = new BindingTypeDescriptor($type);

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($type);

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($typeDescriptor) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($typeDescriptor), $typeDescriptors);
            }));

        $this->manager->addRootTypeDescriptor($typeDescriptor);
    }

    public function testAddRootTypeDescriptorUndefinesTypeIfSavingFails()
    {
        $this->initDefaultManager();

        $existingDescriptor = new BindingTypeDescriptor(new BindingType(Bar::clazz));

        $this->rootModuleFile->addTypeDescriptor($existingDescriptor);

        $typeDescriptor = new BindingTypeDescriptor(new BindingType(Foo::clazz));

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($typeDescriptor->getType());

        $this->discovery->expects($this->once())
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->addRootTypeDescriptor($typeDescriptor);
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existingDescriptor), $this->rootModuleFile->getTypeDescriptors());

        $this->assertTrue($existingDescriptor->isEnabled());
        $this->assertFalse($typeDescriptor->isLoaded());
    }

    public function testRemoveRootTypeDescriptor()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor = new BindingTypeDescriptor($type));

        $this->discovery->expects($this->once())
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);

        $this->assertFalse($typeDescriptor->isLoaded());
    }

    public function testRemoveRootTypeDescriptorIgnoresNonExistingTypes()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeRootTypeDescriptor(Foo::clazz);
    }

    public function testRemoveRootTypeDescriptorIgnoresIfNotInRootModule()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->moduleFile1->addTypeDescriptor($typeDescriptor = new BindingTypeDescriptor($type));

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeRootTypeDescriptor(Foo::clazz);

        $this->assertTrue($typeDescriptor->isEnabled());
    }

    public function testRemoveRootTypeDescriptorDefinesTypeIfResolvingDuplication()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = clone $typeDescriptor1);

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($type);

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);

        $this->assertFalse($typeDescriptor1->isLoaded());
        $this->assertTrue($typeDescriptor2->isEnabled());
    }

    public function testRemoveRootTypeDescriptorDoesNotDefineTypeIfStillDuplicated()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = clone $typeDescriptor1);
        $this->moduleFile2->addTypeDescriptor($typeDescriptor3 = clone $typeDescriptor1);

        $this->discovery->expects($this->never())
            ->method('addBindingType');

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);

        $this->assertFalse($typeDescriptor1->isLoaded());
        $this->assertTrue($typeDescriptor2->isDuplicate());
        $this->assertTrue($typeDescriptor3->isDuplicate());
    }

    public function testRemoveRootTypeDescriptorUnbindsCorrespondingBindings()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);
        $binding = new ResourceBinding('/path', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor($type));
        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->once())
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);
    }

    public function testRemoveRootTypeDescriptorAddsFormerlyIgnoredBindings()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);
        $binding = new ResourceBinding('/path', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = clone $typeDescriptor1);
        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($type);

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($typeDescriptor1) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);
    }

    public function testRemoveRootTypeDescriptorDoesNotAddFormerlyIgnoredBindingsIfStillDuplicated()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);
        $binding = new ResourceBinding('/path', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = clone $typeDescriptor1);
        $this->moduleFile2->addTypeDescriptor($typeDescriptor3 = clone $typeDescriptor1);
        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBindingType');

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($typeDescriptor1) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);
    }

    public function testRemoveRootTypeDescriptorDoesNotEmitWarningForRemovedDuplicateType()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = clone $typeDescriptor1);

        $this->logger->expects($this->never())
            ->method('warning');

        // The descriptor from the module becomes active
        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($typeDescriptor2->getType());

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);
    }

    public function testRemoveRootTypeDescriptorEmitsWarningIfDuplicatedMoreThanOnce()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile2->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $this->logger->expects($this->once())
            ->method('warning');

        $this->discovery->expects($this->never())
            ->method('addBindingType');

        $this->discovery->expects($this->never())
            ->method('removeBindingType');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $typeDescriptors = $rootModuleFile->getTypeDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $typeDescriptors);
            }));

        $this->manager->removeRootTypeDescriptor(Foo::clazz);
    }

    public function testRemoveRootTypeDescriptorDefinesTypeIfSavingFails()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor = new BindingTypeDescriptor($type));

        $this->discovery->expects($this->once())
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($type);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeRootTypeDescriptor(Foo::clazz);
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($typeDescriptor), $this->rootModuleFile->getTypeDescriptors());

        $this->assertTrue($typeDescriptor->isEnabled());
    }

    public function testRemoveRootTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Baz::clazz);
        $type2 = new BindingType(Bar::clazz);
        $type3 = new BindingType(Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->rootModuleFile->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));
        $this->rootModuleFile->addTypeDescriptor($typeDescriptor3 = new BindingTypeDescriptor($type3));

        $this->discovery->expects($this->at(0))
            ->method('removeBindingType')
            ->with(Baz::clazz);

        $this->discovery->expects($this->at(1))
            ->method('removeBindingType')
            ->with(Bar::clazz);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($typeDescriptor3) {
                PHPUnit_Framework_Assert::assertSame(array($typeDescriptor3), $rootModuleFile->getTypeDescriptors());
            }));

        $this->manager->removeRootTypeDescriptors(Expr::method('getTypeName', Expr::contains('Fixtures\Ba')));

        $this->assertFalse($typeDescriptor1->isLoaded());
        $this->assertFalse($typeDescriptor2->isLoaded());
        $this->assertTrue($typeDescriptor3->isLoaded());
    }

    public function testRemoveRootTypeDescriptorsUnbindsCorrespondingBindings()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);
        $bindingDescriptor1 = new ResourceBinding('/path1', Foo::clazz);
        $bindingDescriptor2 = new ResourceBinding('/path2', Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor($type1));
        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor($type2));
        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($bindingDescriptor1));
        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($bindingDescriptor2));

        $this->discovery->expects($this->at(0))
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->discovery->expects($this->at(1))
            ->method('removeBindingType')
            ->with(Bar::clazz);

        $this->discovery->expects($this->at(2))
            ->method('removeBinding')
            ->with($bindingDescriptor1->getUuid());

        $this->discovery->expects($this->at(3))
            ->method('removeBinding')
            ->with($bindingDescriptor2->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasTypeDescriptors());
            }));

        $this->manager->removeRootTypeDescriptors(Expr::method('getTypeName', Expr::startsWith('Puli')));
    }

    public function testClearRootTypeDescriptorsDefinesTypesIfSavingFails()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->rootModuleFile->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));

        $this->discovery->expects($this->at(0))
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->discovery->expects($this->at(1))
            ->method('removeBindingType')
            ->with(Bar::clazz);

        $this->discovery->expects($this->at(2))
            ->method('addBindingType')
            ->with($type2);

        $this->discovery->expects($this->at(3))
            ->method('addBindingType')
            ->with($type1);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeRootTypeDescriptors(Expr::method('getTypeName', Expr::startsWith('Puli')));
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame($typeDescriptor1, $this->rootModuleFile->getTypeDescriptor(Foo::clazz));
        $this->assertSame($typeDescriptor2, $this->rootModuleFile->getTypeDescriptor(Bar::clazz));
        $this->assertCount(2, $this->rootModuleFile->getTypeDescriptors());

        $this->assertTrue($typeDescriptor1->isEnabled());
        $this->assertTrue($typeDescriptor2->isEnabled());
    }

    public function testClearRootTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->rootModuleFile->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));

        $this->discovery->expects($this->at(0))
            ->method('removeBindingType')
            ->with(Foo::clazz);

        $this->discovery->expects($this->at(1))
            ->method('removeBindingType')
            ->with(Bar::clazz);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasTypeDescriptors());
            }));

        $this->manager->clearRootTypeDescriptors();

        $this->assertFalse($typeDescriptor1->isLoaded());
        $this->assertFalse($typeDescriptor2->isLoaded());
    }

    public function testGetRootTypeDescriptor()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addTypeDescriptor($type = new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $this->assertSame($type, $this->manager->getRootTypeDescriptor(Foo::clazz));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testGetRootTypeDescriptorFailsIfNotFoundInRoot()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type));

        $this->manager->getRootTypeDescriptor(Foo::clazz);
    }

    public function testGetRootTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);
        $type3 = new BindingType(Baz::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->rootModuleFile->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));
        $this->moduleFile2->addTypeDescriptor($typeDescriptor3 = new BindingTypeDescriptor($type3));

        $this->assertSame(array(
            $typeDescriptor1,
            $typeDescriptor2,
        ), $this->manager->getRootTypeDescriptors());
    }

    public function testFindRootTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);
        $type3 = new BindingType(Baz::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->rootModuleFile->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor3 = clone $typeDescriptor2);
        $this->moduleFile2->addTypeDescriptor($typeDescriptor4 = new BindingTypeDescriptor($type3));

        $expr1 = Expr::method('getTypeName', Expr::startsWith('Puli'));
        $expr2 = Expr::method('isDuplicate', Expr::same(true));
        $expr3 = $expr1->andX($expr2);

        $this->assertSame(array($typeDescriptor1, $typeDescriptor2), $this->manager->findRootTypeDescriptors($expr1));
        $this->assertSame(array($typeDescriptor2), $this->manager->findRootTypeDescriptors($expr2));
        $this->assertSame(array($typeDescriptor2), $this->manager->findRootTypeDescriptors($expr3));
    }

    public function testHasRootTypeDescriptor()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor($type1));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type2));

        $this->assertTrue($this->manager->hasRootTypeDescriptor(Foo::clazz));
        $this->assertFalse($this->manager->hasRootTypeDescriptor(Bar::clazz));
        $this->assertFalse($this->manager->hasRootTypeDescriptor(Baz::clazz));
    }

    public function testHasRootTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor($type1));
        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor($type2));

        $expr1 = Expr::method('isEnabled', Expr::same(true));
        $expr2 = Expr::method('isDuplicate', Expr::same(true));

        $this->assertTrue($this->manager->hasRootTypeDescriptors());
        $this->assertTrue($this->manager->hasRootTypeDescriptors($expr1));
        $this->assertFalse($this->manager->hasRootTypeDescriptors($expr2));
    }

    public function testGetTypeDescriptor()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));
        $this->moduleFile2->addTypeDescriptor($typeDescriptor3 = clone $typeDescriptor2); // duplicate

        $this->assertSame($typeDescriptor1, $this->manager->getTypeDescriptor(Foo::clazz, 'vendor/root'));
        $this->assertSame($typeDescriptor2, $this->manager->getTypeDescriptor(Bar::clazz, 'vendor/module1'));
        $this->assertSame($typeDescriptor3, $this->manager->getTypeDescriptor(Bar::clazz, 'vendor/module2'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testGetTypeDescriptorFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getTypeDescriptor(Foo::clazz, 'vendor/root');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetTypeDescriptorFailsIfTypeNoString()
    {
        $this->initDefaultManager();

        $this->manager->getTypeDescriptor(1234, 'vendor/root');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetTypeDescriptorFailsIfModuleNoString()
    {
        $this->initDefaultManager();

        $this->manager->getTypeDescriptor(Foo::clazz, 1234);
    }

    public function testGetTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);
        $type3 = new BindingType(Baz::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));
        $this->moduleFile2->addTypeDescriptor($typeDescriptor3 = clone $typeDescriptor2); // duplicate
        $this->moduleFile3->addTypeDescriptor($typeDescriptor4 = new BindingTypeDescriptor($type3));

        $this->assertSame(array(
            $typeDescriptor1,
            $typeDescriptor2,
            $typeDescriptor3,
            $typeDescriptor4,
        ), $this->manager->getTypeDescriptors());
    }

    public function testFindTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);
        $type3 = new BindingType(Baz::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));
        $this->moduleFile2->addTypeDescriptor($typeDescriptor3 = clone $typeDescriptor2); // duplicate
        $this->moduleFile3->addTypeDescriptor($typeDescriptor4 = new BindingTypeDescriptor($type3));

        $expr1 = Expr::method('getContainingModule', Expr::method('getName', Expr::same('vendor/module1')));
        $expr2 = Expr::method('isDuplicate', Expr::same(true));
        $expr3 = $expr1->andX($expr2);

        $this->assertSame(array($typeDescriptor2), $this->manager->findTypeDescriptors($expr1));
        $this->assertSame(array($typeDescriptor2, $typeDescriptor3), $this->manager->findTypeDescriptors($expr2));
        $this->assertSame(array($typeDescriptor2), $this->manager->findTypeDescriptors($expr3));
    }

    public function testHasTypeDescriptor()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor($type1));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type2));

        $this->assertTrue($this->manager->hasTypeDescriptor(Foo::clazz));
        $this->assertTrue($this->manager->hasTypeDescriptor(Bar::clazz, 'vendor/module1'));
        $this->assertFalse($this->manager->hasTypeDescriptor(Bar::clazz, 'vendor/module2'));
        $this->assertFalse($this->manager->hasTypeDescriptor(Baz::clazz));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testHasTypeDescriptorFailsIfModuleNoString()
    {
        $this->initDefaultManager();

        $this->manager->hasTypeDescriptor(Foo::clazz, 1234);
    }

    public function testHasTypeDescriptors()
    {
        $this->initDefaultManager();

        $type1 = new BindingType(Foo::clazz);
        $type2 = new BindingType(Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor($typeDescriptor1 = new BindingTypeDescriptor($type1));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor2 = new BindingTypeDescriptor($type2));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor3 = clone $typeDescriptor2); // duplicate

        $expr1 = Expr::method('getContainingModule', Expr::method('getName', Expr::same('vendor/module1')))
            ->andMethod('isEnabled', Expr::same(true));
        $expr2 = Expr::method('getContainingModule', Expr::method('getName', Expr::same('vendor/module2')))
            ->andMethod('isDuplicate', Expr::same(true));

        $this->assertTrue($this->manager->hasTypeDescriptors());
        $this->assertTrue($this->manager->hasTypeDescriptors($expr1));
        $this->assertFalse($this->manager->hasTypeDescriptors($expr2));
    }

    public function testHasNoTypeDescriptors()
    {
        $this->initDefaultManager();

        $this->assertFalse($this->manager->hasTypeDescriptors());
    }

    public function testAddRootBindingDescriptor()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz, array(new BindingParameter('param')));
        $binding = new ResourceBinding('/path', Foo::clazz, array('param' => 'value'), 'xpath');

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type));

        $bindingDescriptor = new BindingDescriptor($binding);

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($bindingDescriptor) {
                $bindingDescriptors = $rootModuleFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingDescriptor), $bindingDescriptors);
                PHPUnit_Framework_Assert::assertTrue($bindingDescriptor->isEnabled());
            }));

        $this->manager->addRootBindingDescriptor($bindingDescriptor);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testAddRootBindingDescriptorFailsIfUuidDuplicatedInModule()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor1 = new BindingDescriptor($binding);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = clone $bindingDescriptor1);

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor($bindingDescriptor1);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testAddRootBindingDescriptorFailsIfUuidDuplicatedInRoot()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor1 = new BindingDescriptor($binding);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor2 = clone $bindingDescriptor1);

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor($bindingDescriptor1);
    }

    public function testAddRootBindingDescriptorSucceedsIfUuidDuplicatedInRootAndOverride()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz, array(new BindingParameter('param')));
        $uuid = Uuid::uuid4();
        $binding1 = new ResourceBinding('/path1', Foo::clazz, array('param' => 'value'), 'xpath', $uuid);
        $binding2 = new ResourceBinding('/path2', Foo::clazz, array('param' => 'value'), 'xpath', $uuid);
        $bindingDescriptor1 = new BindingDescriptor($binding1);
        $bindingDescriptor2 = new BindingDescriptor($binding2);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1);

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($uuid);

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding2);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($bindingDescriptor2) {
                $bindingDescriptors = $rootModuleFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingDescriptor2), $bindingDescriptors);
                PHPUnit_Framework_Assert::assertTrue($bindingDescriptor2->isEnabled());
            }));

        $this->manager->addRootBindingDescriptor($bindingDescriptor2, DiscoveryManager::OVERRIDE);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DuplicateBindingException
     */
    public function testAddRootBindingDescriptorDoesNotOverrideModuleUuids()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz, array(new BindingParameter('param')));
        $uuid = Uuid::uuid4();
        $binding1 = new ResourceBinding('/path1', Foo::clazz, array('param' => 'value'), 'xpath', $uuid);
        $binding2 = new ResourceBinding('/path2', Foo::clazz, array('param' => 'value'), 'xpath', $uuid);
        $bindingDescriptor1 = new BindingDescriptor($binding1);
        $bindingDescriptor2 = new BindingDescriptor($binding2);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor1);

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor($bindingDescriptor2, DiscoveryManager::OVERRIDE);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testAddRootBindingDescriptorFailsIfTypeNotDefined()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor(new BindingDescriptor(new ResourceBinding('/path', Foo::clazz)));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testAddRootBindingDescriptorFailsIfTypeNotDefinedAndIgnoreNotEnabled()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor(new BindingDescriptor(new ResourceBinding('/path', Foo::clazz)), DiscoveryManager::IGNORE_TYPE_NOT_ENABLED);
    }

    public function testAddRootBindingDescriptorDoesNotFailIfTypeNotDefinedAndIgnoreTypeNotFound()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor = new BindingDescriptor($binding);

        // The type does not exist
        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($bindingDescriptor) {
                $bindingDescriptors = $rootModuleFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingDescriptor), $bindingDescriptors);
                PHPUnit_Framework_Assert::assertTrue($bindingDescriptor->isTypeNotFound());
            }));

        $this->manager->addRootBindingDescriptor($bindingDescriptor, DiscoveryManager::IGNORE_TYPE_NOT_FOUND);
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
     */
    public function testAddRootBindingDescriptorFailsIfTypeNotEnabled()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor(new BindingDescriptor(new ResourceBinding('/path', Foo::clazz)));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
     */
    public function testAddRootBindingDescriptorFailsIfTypeNotEnabledAndIgnoreTypeNotFound()
    {
        $this->initDefaultManager();

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor(new BindingDescriptor(new ResourceBinding('/path', Foo::clazz)), DiscoveryManager::IGNORE_TYPE_NOT_FOUND);
    }

    public function testAddRootBindingDescriptorDoesNotFailIfTypeNotEnabledAndIgnoreTypeNotEnabled()
    {
        $this->initDefaultManager();

        $bindingDescriptor = new BindingDescriptor(new ResourceBinding('/path', Foo::clazz));

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        // The type is not enabled
        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($bindingDescriptor) {
                $bindingDescriptors = $rootModuleFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($bindingDescriptor), $bindingDescriptors);
                PHPUnit_Framework_Assert::assertTrue($bindingDescriptor->isTypeNotEnabled());
            }));

        $this->manager->addRootBindingDescriptor($bindingDescriptor, DiscoveryManager::IGNORE_TYPE_NOT_ENABLED);
    }

    /**
     * @expectedException \Puli\Discovery\Api\Type\MissingParameterException
     */
    public function testAddRootBindingDescriptorFailsIfMissingParameters()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->addRootBindingDescriptor(new BindingDescriptor(new ResourceBinding('/path', Foo::clazz)));
    }

    public function testAddRootBindingDescriptorUnbindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $existingDescriptor = new BindingDescriptor(new ResourceBinding('/existing', Foo::clazz));

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor = new BindingDescriptor($binding);

        $this->rootModuleFile->addBindingDescriptor($existingDescriptor);
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->addRootBindingDescriptor($bindingDescriptor);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existingDescriptor), $this->rootModuleFile->getBindingDescriptors());
        $this->assertFalse($bindingDescriptor->isLoaded());
    }

    public function testRemoveRootBindingDescriptor()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor = new BindingDescriptor($binding);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor);

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $bindingDescriptors = $rootModuleFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindingDescriptors);
            }));

        $this->manager->removeRootBindingDescriptor($binding->getUuid());

        $this->assertFalse($bindingDescriptor->isLoaded());
    }

    public function testRemoveRootBindingDescriptorIgnoresNonExistingBindings()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeRootBindingDescriptor(Uuid::uuid4());
    }

    public function testRemoveRootBindingDescriptorIgnoresIfBindingNotInRootModule()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor = new BindingDescriptor($binding);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor);

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->removeRootBindingDescriptor($binding->getUuid());

        $this->assertTrue($bindingDescriptor->isEnabled());
    }

    public function testRemoveRootBindingDescriptorWithTypeNotFound()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor = new BindingDescriptor($binding);

        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor);

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $bindingDescriptors = $rootModuleFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindingDescriptors);
            }));

        $this->manager->removeRootBindingDescriptor($binding->getUuid());
    }

    public function testRemoveRootBindingDescriptorWithTypeNotEnabled()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor = new BindingDescriptor($binding);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor);

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                $bindingDescriptors = $rootModuleFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array(), $bindingDescriptors);
            }));

        $this->manager->removeRootBindingDescriptor($binding->getUuid());
    }

    public function testRemoveRootBindingDescriptorBindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path', Foo::clazz);
        $bindingDescriptor = new BindingDescriptor($binding);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor);

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeRootBindingDescriptor($binding->getUuid());
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame(array($bindingDescriptor), $this->rootModuleFile->getBindingDescriptors());
        $this->assertTrue($bindingDescriptor->isLoaded());
    }

    public function testRemoveRootBindingDescriptors()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);
        $binding3 = new ResourceBinding('/other3', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor3 = new BindingDescriptor($binding3));

        $this->discovery->expects($this->at(0))
            ->method('removeBinding')
            ->with($binding1->getUuid());

        $this->discovery->expects($this->at(1))
            ->method('removeBinding')
            ->with($binding2->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($bindingDescriptor3) {
                PHPUnit_Framework_Assert::assertSame(array($bindingDescriptor3), $rootModuleFile->getBindingDescriptors());
            }));

        $this->manager->removeRootBindingDescriptors(Expr::method('getBinding', Expr::method('getQuery', Expr::startsWith('/path'))));

        $this->assertFalse($bindingDescriptor1->isLoaded());
        $this->assertFalse($bindingDescriptor2->isLoaded());
        $this->assertTrue($bindingDescriptor3->isLoaded());
    }

    public function testRemoveRootBindingDescriptorsBindsIfSavingFailed()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));

        $this->discovery->expects($this->at(0))
            ->method('removeBinding')
            ->with($binding1->getUuid());

        $this->discovery->expects($this->at(1))
            ->method('removeBinding')
            ->with($binding2->getUuid());

        $this->discovery->expects($this->at(2))
            ->method('addBinding')
            ->with($binding2);

        $this->discovery->expects($this->at(3))
            ->method('addBinding')
            ->with($binding1);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeRootBindingDescriptors(Expr::method('getBinding', Expr::method('getQuery', Expr::startsWith('/path'))));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertSame($bindingDescriptor1, $this->rootModuleFile->getBindingDescriptor($bindingDescriptor1->getUuid()));
        $this->assertSame($bindingDescriptor2, $this->rootModuleFile->getBindingDescriptor($bindingDescriptor2->getUuid()));
        $this->assertCount(2, $this->rootModuleFile->getBindingDescriptors());
        $this->assertTrue($bindingDescriptor1->isLoaded());
        $this->assertTrue($bindingDescriptor2->isLoaded());
    }

    public function testClearRootBindingDescriptors()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));

        $this->discovery->expects($this->at(0))
            ->method('removeBinding')
            ->with($binding1->getUuid());

        $this->discovery->expects($this->at(1))
            ->method('removeBinding')
            ->with($binding2->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) {
                PHPUnit_Framework_Assert::assertFalse($rootModuleFile->hasBindingDescriptors());
            }));

        $this->manager->clearRootBindingDescriptors();

        $this->assertFalse($bindingDescriptor1->isLoaded());
        $this->assertFalse($bindingDescriptor2->isLoaded());
    }

    public function testHasRootBindingDescriptor()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));

        $this->assertTrue($this->manager->hasRootBindingDescriptor($bindingDescriptor1->getUuid()));
        $this->assertFalse($this->manager->hasRootBindingDescriptor($bindingDescriptor2->getUuid()));
        $this->assertFalse($this->manager->hasRootBindingDescriptor(Uuid::fromString(self::NOT_FOUND_UUID)));
    }

    public function testFindRootBindingDescriptors()
    {
        $this->initDefaultManager();

        $uuid1 = Uuid::fromString('f966a2e1-4738-42ac-b007-1ac8798c1877');
        $uuid2 = Uuid::fromString('ecc5bb18-a4be-483d-9682-3999504b80d5');
        $uuid3 = Uuid::fromString('ecc0b0b5-67ff-4b01-9836-9aa4d5136af4');

        $binding1 = new ResourceBinding('/path1', Foo::clazz, array(), 'glob', $uuid1);
        $binding2 = new ResourceBinding('/path2', Foo::clazz, array(), 'glob', $uuid2);
        $binding3 = new ResourceBinding('/path3', Foo::clazz, array(), 'glob', $uuid3);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor3 = new BindingDescriptor($binding3));

        $expr1 = Expr::method('getUuid', Expr::startsWith('ecc'));
        $expr2 = Expr::method('getTypeName', Expr::same(Foo::clazz));
        $expr3 = $expr1->andX($expr2);

        $this->assertSame(array($bindingDescriptor2), $this->manager->findRootBindingDescriptors($expr1));
        $this->assertSame(array($bindingDescriptor1, $bindingDescriptor2), $this->manager->findRootBindingDescriptors($expr2));
        $this->assertSame(array($bindingDescriptor2), $this->manager->findRootBindingDescriptors($expr3));
    }

    public function testHasRootBindingDescriptors()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));

        $expr1 = Expr::method('isEnabled', Expr::same(true));
        $expr2 = Expr::method('isTypeNotFound', Expr::same(true));

        $this->assertTrue($this->manager->hasRootBindingDescriptors());
        $this->assertTrue($this->manager->hasRootBindingDescriptors($expr1));
        $this->assertFalse($this->manager->hasRootBindingDescriptors($expr2));
    }

    public function testEnableBindingDescriptorBindsIfDisabled()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor = new BindingDescriptor($binding));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($binding) {
                $installInfo = $rootModuleFile->getInstallInfo('vendor/module1');
                $disabledBindingUuids = $installInfo->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array(), $disabledBindingUuids);
            }));

        $this->manager->enableBindingDescriptor($binding->getUuid());

        $this->assertTrue($bindingDescriptor->isEnabled());
    }

    public function testEnableBindingDescriptorDoesNothingIfAlreadyEnabled()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor = new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->enableBindingDescriptor($binding->getUuid());

        $this->assertTrue($bindingDescriptor->isEnabled());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchBindingException
     * @expectedExceptionMessage 8546da2c-dfec-48be-8cd3-93798c41b72f
     */
    public function testEnableBindingDescriptorFailsIfBindingNotFound()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->enableBindingDescriptor(Uuid::fromString('8546da2c-dfec-48be-8cd3-93798c41b72f'));
    }

    /**
     * @expectedException \Puli\Manager\Api\NonRootModuleExpectedException
     */
    public function testEnableBindingDescriptorFailsIfBindingInRootModule()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->enableBindingDescriptor($binding->getUuid());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testEnableBindingDescriptorFailsIfTypeNotFound()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->enableBindingDescriptor($binding->getUuid());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
     */
    public function testEnableBindingDescriptorFailsIfTypeNotEnabled()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->enableBindingDescriptor($binding->getUuid());
    }

    public function testEnableBindingDescriptorUnbindsIfSavingFails()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($existingDescriptor = new BindingDescriptor(new ResourceBinding('/existing', Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor = new BindingDescriptor($binding));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->enableBindingDescriptor($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($binding->getUuid()), $this->installInfo1->getDisabledBindingUuids());
    }

    public function testEnableBindingDescriptorRestoresDisabledBindingsIfSavingFails()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor = new BindingDescriptor($binding));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->enableBindingDescriptor($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($binding->getUuid()), $this->installInfo1->getDisabledBindingUuids());
    }

    public function testDisableBindingDescriptorUnbindsIfEnabled()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor = new BindingDescriptor($binding));

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($binding) {
                $installInfo = $rootModuleFile->getInstallInfo('vendor/module1');
                $disabledBindingUuids = $installInfo->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding->getUuid()), $disabledBindingUuids);
            }));

        $this->manager->disableBindingDescriptor($binding->getUuid());

        $this->assertTrue($bindingDescriptor->isDisabled());
    }

    public function testDisableBindingDescriptorDoesNothingIfAlreadyDisabled()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor = new BindingDescriptor($binding));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->disableBindingDescriptor($binding->getUuid());

        $this->assertTrue($bindingDescriptor->isDisabled());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchBindingException
     * @expectedExceptionMessage 8546da2c-dfec-48be-8cd3-93798c41b72f
     */
    public function testDisableBindingDescriptorFailsIfBindingNotFound()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->disableBindingDescriptor(Uuid::fromString('8546da2c-dfec-48be-8cd3-93798c41b72f'));
    }

    /**
     * @expectedException \Puli\Manager\Api\NonRootModuleExpectedException
     */
    public function testDisableBindingDescriptorFailsIfBindingInRootModule()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->disableBindingDescriptor($binding->getUuid());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchTypeException
     */
    public function testDisableBindingDescriptorFailsIfTypeNotFound()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->disableBindingDescriptor($binding->getUuid());
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\TypeNotEnabledException
     */
    public function testDisableBindingDescriptorFailsIfTypeNotEnabled()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('removeBinding');

        $this->moduleFileStorage->expects($this->never())
            ->method('saveRootModuleFile');

        $this->manager->disableBindingDescriptor($binding->getUuid());
    }

    public function testDisableBindingDescriptorRebindsIfSavingFails()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);
        $existing = new ResourceBinding('/existing', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($existing));
        $this->installInfo1->addDisabledBindingUuid($existing->getUuid());
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->disableBindingDescriptor($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($existing->getUuid()), $this->installInfo1->getDisabledBindingUuids());
    }

    public function testDisableBindingDescriptorRestoresEnabledBindingsIfSavingFails()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->once())
            ->method('removeBinding')
            ->with($binding->getUuid());

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->disableBindingDescriptor($binding->getUuid());
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array(), $this->installInfo1->getDisabledBindingUuids());
    }

    public function testRemoveObsoleteDisabledBindingDescriptors()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());
        $this->installInfo1->addDisabledBindingUuid(Uuid::uuid4());
        $this->installInfo2->addDisabledBindingUuid(Uuid::uuid4());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->will($this->returnCallback(function (RootModuleFile $rootModuleFile) use ($binding) {
                $installInfo1 = $rootModuleFile->getInstallInfo('vendor/module1');
                $installInfo2 = $rootModuleFile->getInstallInfo('vendor/module2');
                $disabledBindingUuids1 = $installInfo1->getDisabledBindingUuids();
                $disabledBindingUuids2 = $installInfo2->getDisabledBindingUuids();

                PHPUnit_Framework_Assert::assertSame(array($binding->getUuid()), $disabledBindingUuids1);
                PHPUnit_Framework_Assert::assertSame(array(), $disabledBindingUuids2);
            }));

        $this->manager->removeObsoleteDisabledBindingDescriptors();
    }

    public function testRemoveObsoleteDisabledBindingDescriptorsRevertsIfSavingFails()
    {
        $this->initDefaultManager();

        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));
        $this->installInfo1->addDisabledBindingUuid($binding->getUuid());
        $this->installInfo1->addDisabledBindingUuid($uuid1 = Uuid::uuid4());
        $this->installInfo2->addDisabledBindingUuid($uuid2 = Uuid::uuid4());

        $this->moduleFileStorage->expects($this->once())
            ->method('saveRootModuleFile')
            ->with($this->rootModuleFile)
            ->willThrowException(new TestException('Some exception'));

        try {
            $this->manager->removeObsoleteDisabledBindingDescriptors();
            $this->fail('Expected an exception');
        } catch (TestException $e) {
        }

        $this->assertSame(array($binding->getUuid(), $uuid1), $this->installInfo1->getDisabledBindingUuids());
        $this->assertSame(array($uuid2), $this->installInfo2->getDisabledBindingUuids());
    }

    public function testGetBindingDescriptor()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));

        $this->assertSame($bindingDescriptor1, $this->manager->getBindingDescriptor($binding1->getUuid()));
        $this->assertSame($bindingDescriptor2, $this->manager->getBindingDescriptor($binding2->getUuid()));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchBindingException
     */
    public function testGetBindingDescriptorFailsIfNotFound()
    {
        $this->initDefaultManager();

        $this->manager->getBindingDescriptor(Uuid::fromString(self::NOT_FOUND_UUID));
    }

    public function testGetBindingDescriptors()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);
        $binding3 = new ResourceBinding('/path3', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));
        $this->installInfo1->addDisabledBindingUuid($binding2->getUuid());
        $this->moduleFile3->addBindingDescriptor($bindingDescriptor3 = new BindingDescriptor($binding3));

        $this->assertSame(array(
            $bindingDescriptor1,
            $bindingDescriptor2,
            $bindingDescriptor3,
        ), $this->manager->getBindingDescriptors());
    }

    public function testFindBindingDescriptors()
    {
        $this->initDefaultManager();

        $uuid1 = Uuid::fromString('f966a2e1-4738-42ac-b007-1ac8798c1877');
        $uuid2 = Uuid::fromString('ecc5bb18-a4be-483d-9682-3999504b80d5');
        $uuid3 = Uuid::fromString('ecc0b0b5-67ff-4b01-9836-9aa4d5136af4');

        $binding1 = new ResourceBinding('/path1', Foo::clazz, array(), 'glob', $uuid1);
        $binding2 = new ResourceBinding('/path2', Foo::clazz, array(), 'glob', $uuid2);
        $binding3 = new ResourceBinding('/path3', Foo::clazz, array(), 'glob', $uuid3);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));
        $this->moduleFile2->addBindingDescriptor($bindingDescriptor3 = new BindingDescriptor($binding3));

        $expr1 = Expr::method('getUuid', Expr::startsWith('ecc'));
        $expr2 = $expr1->andMethod('getContainingModule', Expr::method('getName', Expr::same('vendor/module1')));
        $expr3 = $expr1->andMethod('getContainingModule', Expr::method('getName', Expr::same('vendor/root')));

        $this->assertSame(array($bindingDescriptor2, $bindingDescriptor3), $this->manager->findBindingDescriptors($expr1));
        $this->assertSame(array($bindingDescriptor2), $this->manager->findBindingDescriptors($expr2));
        $this->assertSame(array(), $this->manager->findBindingDescriptors($expr3));
    }

    public function testHasBindingDescriptor()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));

        $this->assertTrue($this->manager->hasBindingDescriptor($bindingDescriptor1->getUuid()));
        $this->assertTrue($this->manager->hasBindingDescriptor($bindingDescriptor2->getUuid()));
        $this->assertFalse($this->manager->hasBindingDescriptor(Uuid::fromString(self::NOT_FOUND_UUID)));
    }

    public function testHasBindingDescriptors()
    {
        $this->initDefaultManager();

        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Bar::clazz);

        $this->rootModuleFile->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));

        $expr1 = Expr::method('getContainingModule', Expr::method('getName', Expr::same('vendor/module1')));
        $expr2 = Expr::method('isEnabled', Expr::same(true));
        $expr3 = $expr1->andX($expr2);

        $this->assertTrue($this->manager->hasBindingDescriptors());
        $this->assertTrue($this->manager->hasBindingDescriptors($expr1));
        $this->assertTrue($this->manager->hasBindingDescriptors($expr2));
        $this->assertFalse($this->manager->hasBindingDescriptors($expr3));
    }

    public function testHasNoBindingDescriptors()
    {
        $this->initDefaultManager();

        $this->assertFalse($this->manager->hasBindingDescriptors());
    }

    public function testBuildDiscovery()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);
        $binding = new ResourceBinding('/path', Foo::clazz);

        $this->rootModuleFile->addBindingDescriptor($bindingDescriptor = new BindingDescriptor($binding));
        $this->moduleFile1->addTypeDescriptor($typeDescriptor = new BindingTypeDescriptor($type));

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($type);

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding);

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryOnlyIncludesEnabledBindingsOfInstalledModules()
    {
        $this->initDefaultManager();

        $type = new BindingType(Foo::clazz);
        $binding1 = new ResourceBinding('/path1', Foo::clazz);
        $binding2 = new ResourceBinding('/path2', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor($typeDescriptor = new BindingTypeDescriptor($type));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor1 = new BindingDescriptor($binding1));
        $this->moduleFile1->addBindingDescriptor($bindingDescriptor2 = new BindingDescriptor($binding2));
        $this->installInfo1->addDisabledBindingUuid($bindingDescriptor2->getUuid());

        $this->discovery->expects($this->once())
            ->method('addBindingType')
            ->with($type);

        $this->discovery->expects($this->once())
            ->method('addBinding')
            ->with($binding1);

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryDoesNotAddBindingsForUnknownTypes()
    {
        $this->initDefaultManager();

        // The type could be defined in an optional module
        $binding = new ResourceBinding('/path', Foo::clazz);

        $this->rootModuleFile->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningsForBindingsWithUnknownParameters()
    {
        $this->initDefaultManager();

        // Unknown parameter
        $type = new BindingType(Foo::clazz);
        $binding = new ResourceBinding('/path1', Foo::clazz, array('param' => 'value'));

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->matchesRegularExpression('~.*"param" does not exist.*~'));

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningsForBindingsWithMissingParameters()
    {
        $this->initDefaultManager();

        // Required parameter is missing
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));
        $binding = new ResourceBinding('/path1', Foo::clazz);

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor($type));
        $this->moduleFile1->addBindingDescriptor(new BindingDescriptor($binding));

        $this->discovery->expects($this->never())
            ->method('addBinding');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->matchesRegularExpression('~.*"param" is required.*~'));

        $this->manager->buildDiscovery();
    }

    public function testBuildDiscoveryEmitsWarningsForDuplicatedTypes()
    {
        $this->initDefaultManager();

        $this->moduleFile1->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));
        $this->moduleFile2->addTypeDescriptor(new BindingTypeDescriptor(new BindingType(Foo::clazz)));

        $this->discovery->expects($this->never())
            ->method('addBindingType');

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
            ->method('hasBindings')
            ->willReturn(true);

        $this->discovery->expects($this->never())
            ->method('addBindingType');

        $this->manager->buildDiscovery();
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\DiscoveryNotEmptyException
     */
    public function testBuildDiscoveryFailsIfDiscoveryContainsTypes()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->once())
            ->method('hasBindingTypes')
            ->willReturn(true);

        $this->discovery->expects($this->never())
            ->method('addBindingType');

        $this->manager->buildDiscovery();
    }

    public function testClearDiscovery()
    {
        $this->initDefaultManager();

        $this->discovery->expects($this->once())
            ->method('removeBindingTypes');

        $this->manager->clearDiscovery();
    }

    private function initDefaultManager()
    {
        $this->rootModuleFile->addInstallInfo($this->installInfo1);
        $this->rootModuleFile->addInstallInfo($this->installInfo2);
        $this->rootModuleFile->addInstallInfo($this->installInfo3);

        $this->modules->add(new RootModule($this->rootModuleFile, $this->rootDir));
        $this->modules->add(new Module($this->moduleFile1, $this->moduleDir1, $this->installInfo1));
        $this->modules->add(new Module($this->moduleFile2, $this->moduleDir2, $this->installInfo2));
        $this->modules->add(new Module($this->moduleFile3, $this->moduleDir3, $this->installInfo3));

        $this->manager = new DiscoveryManagerImpl($this->context, $this->discovery, $this->modules, $this->moduleFileStorage, $this->logger);
    }
}
