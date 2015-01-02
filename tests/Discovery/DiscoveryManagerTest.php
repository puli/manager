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
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
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
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PackageFileStorage
     */
    private $packageFileStorage;

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

        $this->packageFile1 = new PackageFile('package1');
        $this->packageFile2 = new PackageFile('package2');
        $this->packageFile3 = new PackageFile('package3');

        $this->packages = new PackageCollection();

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

        $this->discovery->expects($this->once())
            ->method('isDefined')
            ->with('my/type')
            ->will($this->returnValue(true));

        $this->discovery->expects($this->never())
            ->method('define');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBindingType($bindingType);
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

        $binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->any())
            ->method('isDefined')
            ->with('my/type')
            ->will($this->returnValue(true));

        $this->discovery->expects($this->once())
            ->method('bind')
            ->with('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->packageFileStorage->expects($this->once())
            ->method('saveRootPackageFile')
            ->with($this->rootPackageFile)
            ->will($this->returnCallback(function (RootPackageFile $rootPackageFile) use ($binding) {
                $bindings = $rootPackageFile->getBindingDescriptors();

                PHPUnit_Framework_Assert::assertSame(array($binding), $bindings);
            }));

        $this->manager->addBinding($binding);
    }

    /**
     * @expectedException \Puli\Discovery\Api\NoSuchTypeException
     */
    public function testAddBindingFailsIfTypeNotDefined()
    {
        $this->initDefaultManager();

        $binding = new BindingDescriptor('/path', 'my/type', array('param' => 'value'), 'xpath');

        $this->discovery->expects($this->any())
            ->method('isDefined')
            ->with('my/type')
            ->will($this->returnValue(false));

        $this->discovery->expects($this->never())
            ->method('bind');

        $this->packageFileStorage->expects($this->never())
            ->method('saveRootPackageFile');

        $this->manager->addBinding($binding);
    }

    public function testGetBindings()
    {
        $this->initDefaultManager();

        $this->rootPackageFile->addBindingDescriptor($binding1 = new BindingDescriptor('/path1', 'my/type1'));
        $this->packageFile1->addBindingDescriptor($binding2 = new BindingDescriptor('/path2', 'my/type2'));
        $this->packageFile2->addBindingDescriptor($binding3 = new BindingDescriptor('/path3', 'my/type3'));
        $this->packageFile3->addBindingDescriptor($binding4 = new BindingDescriptor('/path4', 'my/type4'));

        $this->assertSame(array(
            $binding1,
            $binding2,
            $binding3,
            $binding4,
        ), $this->manager->getBindings());

        $this->assertSame(array($binding2), $this->manager->getBindings('package1'));
        $this->assertSame(array($binding2, $binding3), $this->manager->getBindings(array('package1', 'package2')));
    }

    private function initDefaultManager()
    {
        $this->packages->add(new RootPackage($this->rootPackageFile, $this->rootDir));
        $this->packages->add(new Package($this->packageFile1, $this->packageDir1));
        $this->packages->add(new Package($this->packageFile2, $this->packageDir2));
        $this->packages->add(new Package($this->packageFile3, $this->packageDir3));

        $this->manager = new DiscoveryManager($this->environment, $this->packages, $this->packageFileStorage);
    }
}
