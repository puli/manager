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

    public function testToBindingParameter()
    {
        $descriptor = new BindingParameterDescriptor('param', false, 'default', 'The description');

        $parameter = $descriptor->toBindingParameter();

        $this->assertInstanceOf('Puli\Discovery\Api\BindingParameter', $parameter);
        $this->assertSame('param', $parameter->getName());
        $this->assertFalse($parameter->isRequired());
        $this->assertSame('default', $parameter->getDefaultValue());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNameMustBeString()
    {
        new BindingParameterDescriptor(1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNameMustNotBeEmpty()
    {
        new BindingParameterDescriptor('');
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
}
