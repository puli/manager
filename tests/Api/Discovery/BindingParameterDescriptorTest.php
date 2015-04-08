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
use Puli\Manager\Api\Discovery\BindingParameterDescriptor;
use stdClass;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingParameterDescriptorTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $descriptor = new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED, null, 'The description');

        $this->assertSame('param', $descriptor->getName());
        $this->assertTrue($descriptor->isRequired());
        $this->assertNull($descriptor->getDefaultValue());
        $this->assertSame('The description', $descriptor->getDescription());
    }

    public function testCreateWithDefaultValues()
    {
        $descriptor = new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default');

        $this->assertSame('param', $descriptor->getName());
        $this->assertFalse($descriptor->isRequired());
        $this->assertSame('default', $descriptor->getDefaultValue());
        $this->assertNull($descriptor->getDescription());
    }

    public function testOptionalIfFlagsNull()
    {
        $descriptor = new BindingParameterDescriptor('param', null);

        $this->assertFalse($descriptor->isRequired());
    }

    public function getValidNames()
    {
        return array(
            array('param'),
            array('param-name'),
            array('param-name-123'),
        );
    }

    /**
     * @dataProvider getValidNames
     */
    public function testValidName($name)
    {
        $descriptor = new BindingParameterDescriptor($name);

        $this->assertSame($name, $descriptor->getName());
    }

    public function getInvalidNames()
    {
        return array(
            array(1234),
            array(''),
            array('paramName'),
            array('my/param'),
            array('123digitsfirst'),
        );
    }

    /**
     * @dataProvider getInvalidNames
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInvalidName($name)
    {
        new BindingParameterDescriptor($name);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRequiredMustBeIntOrNull()
    {
        new BindingParameterDescriptor('param', true);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustBeStringOrNull()
    {
        new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, null, 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustNotBeEmpty()
    {
        new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, null, '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDefaultValueMustNotBeObject()
    {
        new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, new stdClass());
    }

    /**
     * @dataProvider getValidNames
     */
    public function testToBindingParameter($name)
    {
        // Check that valid names are also accepted by BindingParameter
        $descriptor = new BindingParameterDescriptor($name, BindingParameterDescriptor::OPTIONAL, 'default', 'The description');

        $parameter = $descriptor->toBindingParameter();

        $this->assertInstanceOf('Puli\Discovery\Api\Binding\BindingParameter', $parameter);
        $this->assertSame($name, $parameter->getName());
        $this->assertFalse($parameter->isRequired());
        $this->assertSame('default', $parameter->getDefaultValue());
    }

    public function testToBindingParameterRequired()
    {
        $descriptor = new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED);

        $parameter = $descriptor->toBindingParameter();

        $this->assertInstanceOf('Puli\Discovery\Api\Binding\BindingParameter', $parameter);
        $this->assertSame('param', $parameter->getName());
        $this->assertTrue($parameter->isRequired());
    }
}
