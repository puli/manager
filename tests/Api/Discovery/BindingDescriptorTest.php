<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Discovery;

use PHPUnit_Framework_TestCase;
use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Tests\Fixtures\Foo;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorTest extends PHPUnit_Framework_TestCase
{
    private $uuid;

    /**
     * @var Package
     */
    private $package;

    /**
     * @var Package
     */
    private $rootPackage;

    protected function setUp()
    {
        $this->uuid = Uuid::uuid4();
        $this->package = new Package(new PackageFile(), '/path', new InstallInfo('vendor/package', '/path'));
        $this->rootPackage = new RootPackage(new RootPackageFile(), '/root');
    }

    public function testCreate()
    {
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);

        $this->assertSame($binding, $descriptor->getBinding());
    }

    public function testLoadInitializesBinding()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('foo', BindingParameter::OPTIONAL, 'bar'),
            new BindingParameter('param', BindingParameter::OPTIONAL, 'default'),
        ));

        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->package);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);

        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package, $typeDescriptor);

        $this->assertTrue($binding->isInitialized());
        $this->assertSame($type, $binding->getType());
    }

    public function testTypeNotFoundIfTypeIsNull()
    {
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package);

        $this->assertSame(BindingState::TYPE_NOT_FOUND, $descriptor->getState());
    }

    public function testTypeNotFoundIfTypeIsNotLoaded()
    {
        $type = new BindingType(Foo::clazz);
        $typeDescriptor = new BindingTypeDescriptor($type);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package, $typeDescriptor);

        $this->assertSame(BindingState::TYPE_NOT_FOUND, $descriptor->getState());
    }

    public function testTypeNotEnabledIfTypeIsNotEnabled()
    {
        $type = new BindingType(Foo::clazz);
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->package);
        $typeDescriptor->markDuplicate(true);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package, $typeDescriptor);

        $this->assertSame(BindingState::TYPE_NOT_ENABLED, $descriptor->getState());
    }

    public function testInvalidIfInvalidParameter()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->package);

        // Parameter is missing
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package, $typeDescriptor);

        $this->assertSame(BindingState::INVALID, $descriptor->getState());
        $this->assertCount(1, $descriptor->getLoadErrors());
    }

    public function testParametersNotValidatedIfTypeNotEnabled()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->package);
        $typeDescriptor->markDuplicate(true);

        // Parameter is missing
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package, $typeDescriptor);

        $this->assertSame(BindingState::TYPE_NOT_ENABLED, $descriptor->getState());
        $this->assertCount(0, $descriptor->getLoadErrors());
    }

    public function testEnabledInRootPackage()
    {
        $type = new BindingType(Foo::clazz);
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->package);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->rootPackage, $typeDescriptor);

        $this->assertSame(BindingState::ENABLED, $descriptor->getState());
    }

    public function testDisabledIfDisabled()
    {
        $type = new BindingType(Foo::clazz);
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->package);

        $this->package->getInstallInfo()->addDisabledBindingUuid($this->uuid);

        $binding = new ClassBinding(__CLASS__, Foo::clazz, array(), $this->uuid);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package, $typeDescriptor);

        $this->assertSame(BindingState::DISABLED, $descriptor->getState());
    }

    public function testEnabledIfNotDisabled()
    {
        $type = new BindingType(Foo::clazz);
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->package);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->package, $typeDescriptor);

        $this->assertSame(BindingState::ENABLED, $descriptor->getState());
    }
}
