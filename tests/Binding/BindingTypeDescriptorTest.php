<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Binding;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Binding\BindingParameterDescriptor;
use Puli\RepositoryManager\Binding\BindingTypeDescriptor;

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
    public function testToBindingType()
    {
        $descriptor = new BindingTypeDescriptor('vendor/type', 'The description.', array(
            $param = new BindingParameterDescriptor('param'),
        ));

        $type = $descriptor->toBindingType();

        $this->assertInstanceOf('Puli\Discovery\Api\BindingType', $type);
        $this->assertSame('vendor/type', $type->getName());
        $this->assertCount(1, $type->getParameters());
        $this->assertInstanceOf('Puli\Discovery\Api\BindingParameter', $type->getParameter('param'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTypeMustBeString()
    {
        new BindingTypeDescriptor(1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTypeMustNotBeEmpty()
    {
        new BindingTypeDescriptor('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTypeMustContainVendorName()
    {
        new BindingTypeDescriptor('foo');
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
}
