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
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorTest extends PHPUnit_Framework_TestCase
{
    const CLASS_BINDING = 'Puli\Discovery\Binding\ClassBinding';

    /**
     * @var Module
     */
    private $module;

    /**
     * @var Module
     */
    private $rootModule;

    protected function setUp()
    {
        $this->module = new Module(new ModuleFile(), '/path', new InstallInfo('vendor/module', '/path'));
        $this->rootModule = new RootModule(new RootModuleFile(), '/root');
    }

    public function testCreate()
    {
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);

        $this->assertSame($binding, $descriptor->getBinding());
    }

    public function testLoadInitializesBinding()
    {
        $type = new BindingType(Foo::clazz, self::CLASS_BINDING, array(
            new BindingParameter('foo', BindingParameter::OPTIONAL, 'bar'),
            new BindingParameter('param', BindingParameter::OPTIONAL, 'default'),
        ));

        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->module);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);

        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->module, $typeDescriptor);

        $this->assertTrue($binding->isInitialized());
        $this->assertSame($type, $binding->getType());
    }

    public function testTypeNotFoundIfTypeIsNull()
    {
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->module);

        $this->assertSame(BindingState::TYPE_NOT_FOUND, $descriptor->getState());
    }

    public function testTypeNotFoundIfTypeIsNotLoaded()
    {
        $type = new BindingType(Foo::clazz, self::CLASS_BINDING);
        $typeDescriptor = new BindingTypeDescriptor($type);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->module, $typeDescriptor);

        $this->assertSame(BindingState::TYPE_NOT_FOUND, $descriptor->getState());
    }

    public function testTypeNotEnabledIfTypeIsNotEnabled()
    {
        $type = new BindingType(Foo::clazz, self::CLASS_BINDING);
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->module);
        $typeDescriptor->markDuplicate(true);

        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->module, $typeDescriptor);

        $this->assertSame(BindingState::TYPE_NOT_ENABLED, $descriptor->getState());
    }

    public function testInvalidIfInvalidParameter()
    {
        $type = new BindingType(Foo::clazz, self::CLASS_BINDING, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->module);

        // Parameter is missing
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->module, $typeDescriptor);

        $this->assertSame(BindingState::INVALID, $descriptor->getState());
        $this->assertCount(1, $descriptor->getLoadErrors());
    }

    public function testParametersNotValidatedIfTypeNotEnabled()
    {
        $type = new BindingType(Foo::clazz, self::CLASS_BINDING, array(
            new BindingParameter('param', BindingParameter::REQUIRED),
        ));
        $typeDescriptor = new BindingTypeDescriptor($type);
        $typeDescriptor->load($this->module);
        $typeDescriptor->markDuplicate(true);

        // Parameter is missing
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);
        $descriptor->load($this->module, $typeDescriptor);

        $this->assertSame(BindingState::TYPE_NOT_ENABLED, $descriptor->getState());
        $this->assertCount(0, $descriptor->getLoadErrors());
    }
}
