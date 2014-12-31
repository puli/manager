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

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingParameterDescriptorTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $descriptor = new BindingParameterDescriptor('param', true, null, 'The description');

        $this->assertSame('param', $descriptor->getName());
        $this->assertTrue($descriptor->isRequired());
        $this->assertNull($descriptor->getDefaultValue());
        $this->assertSame('The description', $descriptor->getDescription());
    }

    public function testCreateWithDefaultValues()
    {
        $descriptor = new BindingParameterDescriptor('param', false, 'default');

        $this->assertSame('param', $descriptor->getName());
        $this->assertFalse($descriptor->isRequired());
        $this->assertSame('default', $descriptor->getDefaultValue());
        $this->assertNull($descriptor->getDescription());
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
    public function testRequiredMustBeBool()
    {
        new BindingParameterDescriptor('param', 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustBeStringOrNull()
    {
        new BindingParameterDescriptor('param', false, null, 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDescriptionMustNotBeEmpty()
    {
        new BindingParameterDescriptor('param', false, null, '');
    }

    /**
     * @dataProvider getValidNames
     */
    public function testToBindingParameter($name)
    {
        // Check that valid names are also accepted by BindingParameter
        $descriptor = new BindingParameterDescriptor($name, false, 'default', 'The description');

        $parameter = $descriptor->toBindingParameter();

        $this->assertInstanceOf('Puli\Discovery\Api\BindingParameter', $parameter);
        $this->assertSame($name, $parameter->getName());
        $this->assertFalse($parameter->isRequired());
        $this->assertSame('default', $parameter->getDefaultValue());
    }
}
