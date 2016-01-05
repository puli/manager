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
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeState;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingTypeDescriptorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Package
     */
    private $package;

    protected function setUp()
    {
        $this->package = new Package(new PackageFile(), '/path', new InstallInfo('vendor/package', '/path'));
    }

    public function testCreate()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param'),
        ));

        $descriptor = new BindingTypeDescriptor($type, 'The description.');

        $this->assertSame($type, $descriptor->getType());
        $this->assertSame('The description.', $descriptor->getDescription());
        $this->assertSame(array(), $descriptor->getParameterDescriptions());
        $this->assertFalse($descriptor->hasParameterDescription('param'));
        $this->assertFalse($descriptor->hasParameterDescription('foo'));
        $this->assertFalse($descriptor->hasParameterDescriptions());
    }

    public function testCreateWithParameterDescription()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param'),
        ));

        $descriptor = new BindingTypeDescriptor($type, 'The description.', array(
            'param' => 'The parameter description.',
        ));

        $this->assertSame(array('param' => 'The parameter description.'), $descriptor->getParameterDescriptions());
        $this->assertSame('The parameter description.', $descriptor->getParameterDescription('param'));
        $this->assertTrue($descriptor->hasParameterDescription('param'));
        $this->assertTrue($descriptor->hasParameterDescriptions());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustBeStringOrNull()
    {
        new BindingTypeDescriptor(new BindingType(Foo::clazz), 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustNotBeEmpty()
    {
        new BindingTypeDescriptor(new BindingType(Foo::clazz), '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterDescriptionsMustBeStringOrNull()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param'),
        ));

        new BindingTypeDescriptor($type, null, array('param' => 1234));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterDescriptionsMustNotBeEmpty()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param'),
        ));

        new BindingTypeDescriptor($type, null, array('param' => ''));
    }

    /**
     * @expectedException \Puli\Discovery\Api\Type\NoSuchParameterException
     * @expectedExceptionMessage foo
     */
    public function testCreateFailsIfInvalidParameter()
    {
        $type = new BindingType(Foo::clazz);

        new BindingTypeDescriptor($type, null, array('foo' => 'The parameter description.'));
    }

    /**
     * @expectedException \Puli\Discovery\Api\Type\NoSuchParameterException
     */
    public function testGetParameterDescriptionFailsIfUnknownParameter()
    {
        $descriptor = new BindingTypeDescriptor(new BindingType(Foo::clazz));

        $descriptor->getParameterDescription('foobar');
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetParameterDescriptionFailsIfUndescribedParameter()
    {
        $type = new BindingType(Foo::clazz, array(
            new BindingParameter('param'),
        ));

        $descriptor = new BindingTypeDescriptor($type);

        $descriptor->getParameterDescription('param');
    }

    public function testEnabledIfLoaded()
    {
        $descriptor = new BindingTypeDescriptor(new BindingType(Foo::clazz));
        $descriptor->load($this->package);

        $this->assertSame(BindingTypeState::ENABLED, $descriptor->getState());
    }

    public function testDuplicateIfMarkedDuplicate()
    {
        $descriptor = new BindingTypeDescriptor(new BindingType(Foo::clazz));
        $descriptor->load($this->package);
        $descriptor->markDuplicate(true);

        $this->assertSame(BindingTypeState::DUPLICATE, $descriptor->getState());
    }
}
