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
use Puli\RepositoryManager\Binding\BindingDescriptor;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $descriptor = new BindingDescriptor('/path', 'type', array(
            'param' => 'value',
        ));

        $this->assertSame('/path', $descriptor->getSelector());
        $this->assertSame('type', $descriptor->getTypeName());
        $this->assertSame(array('param' => 'value'), $descriptor->getParameters());
        $this->assertSame('value', $descriptor->getParameter('param'));
        $this->assertTrue($descriptor->hasParameter('param'));
        $this->assertFalse($descriptor->hasParameter('foobar'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSelectorMustBeString()
    {
        new BindingDescriptor(12345, 'type');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSelectorMustNotBeEmpty()
    {
        new BindingDescriptor('', 'type');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTypeNameMustBeString()
    {
        new BindingDescriptor('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTypeNameMustNotBeEmpty()
    {
        new BindingDescriptor('/path', '');
    }

    /**
     * @expectedException \Puli\Discovery\Api\NoSuchParameterException
     */
    public function testGetParameterFailsIfUnknownParameter()
    {
        $descriptor = new BindingDescriptor('/path', 'type');

        $descriptor->getParameter('foobar');
    }
}
