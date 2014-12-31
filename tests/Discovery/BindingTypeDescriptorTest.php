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

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingTypeDescriptorTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $descriptor = new BindingTypeDescriptor('vendor/type', 'The description.', array(
            $param = new BindingParameterDescriptor('param'),
        ));

        $this->assertSame('vendor/type', $descriptor->getName());
        $this->assertSame('The description.', $descriptor->getDescription());
        $this->assertSame(array('param' => $param), $descriptor->getParameters());
        $this->assertSame($param, $descriptor->getParameter('param'));
        $this->assertTrue($descriptor->hasParameter('param'));
        $this->assertFalse($descriptor->hasParameter('foo'));
    }

    public function testCreateWithDefaultValues()
    {
        $descriptor = new BindingTypeDescriptor('vendor/type');

        $this->assertSame('vendor/type', $descriptor->getName());
        $this->assertNull($descriptor->getDescription());
        $this->assertSame(array(), $descriptor->getParameters());
    }

    public function getValidNames()
    {
        return array(
            array('my/type'),
            array('my/type-name'),
            array('my/type-name'),
            array('my123/type-name-123')
        );
    }

    /**
     * @dataProvider getValidNames
     */
    public function testValidName($name)
    {
        $descriptor = new BindingTypeDescriptor($name);

        $this->assertSame($name, $descriptor->getName());
    }

    public function getInvalidNames()
    {
        return array(
            array(1234),
            array(''),
            array('no-vendor'),
            array('my/Type'),
            array('my/type_name'),
            array('123my/digits-first'),
        );
    }

    /**
     * @dataProvider getInvalidNames
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInvalidName($name)
    {
        new BindingTypeDescriptor($name);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustBeStringOrNull()
    {
        new BindingTypeDescriptor('vendor/type', 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustNotBeEmpty()
    {
        new BindingTypeDescriptor('vendor/type', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParametersMustBeValidInstances()
    {
        new BindingTypeDescriptor('vendor/type', null, array(new \stdClass()));
    }

    /**
     * @expectedException \Puli\Discovery\Api\NoSuchParameterException
     */
    public function testGetParameterFailsIfUnknownParameter()
    {
        $descriptor = new BindingTypeDescriptor('vendor/type');

        $descriptor->getParameter('foobar');
    }

    /**
     * @dataProvider getValidNames
     */
    public function testToBindingType($name)
    {
        // Check that valid names are also accepted by BindingType
        $descriptor = new BindingTypeDescriptor($name, 'The description.', array(
            $param = new BindingParameterDescriptor('param'),
        ));

        $type = $descriptor->toBindingType();

        $this->assertInstanceOf('Puli\Discovery\Api\BindingType', $type);
        $this->assertSame($name, $type->getName());
        $this->assertCount(1, $type->getParameters());
        $this->assertInstanceOf('Puli\Discovery\Api\BindingParameter', $type->getParameter('param'));
    }
}
