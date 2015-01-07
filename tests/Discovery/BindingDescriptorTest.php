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
use Puli\RepositoryManager\Discovery\BindingDescriptor;
use Rhumsaa\Uuid\Uuid;
use stdClass;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorTest extends PHPUnit_Framework_TestCase
{
    private $uuid;

    protected function setUp()
    {
        $this->uuid = Uuid::uuid4();
    }

    public function testCreate()
    {
        $descriptor = new BindingDescriptor($this->uuid, '/path', 'vendor/type', array(
            'param' => 'value',
        ));

        $this->assertSame($this->uuid, $descriptor->getUuid());
        $this->assertSame('/path', $descriptor->getQuery());
        $this->assertSame('glob', $descriptor->getLanguage());
        $this->assertSame('vendor/type', $descriptor->getTypeName());
        $this->assertSame(array('param' => 'value'), $descriptor->getParameters());
        $this->assertSame('value', $descriptor->getParameter('param'));
        $this->assertTrue($descriptor->hasParameter('param'));
        $this->assertFalse($descriptor->hasParameter('foobar'));
        $this->assertTrue($descriptor->hasParameters());
    }

    public function testCreateWithQueryLanguage()
    {
        $descriptor = new BindingDescriptor($this->uuid, '/path', 'vendor/type', array(), 'xpath');

        $this->assertSame($this->uuid, $descriptor->getUuid());
        $this->assertSame('/path', $descriptor->getQuery());
        $this->assertSame('xpath', $descriptor->getLanguage());
        $this->assertSame('vendor/type', $descriptor->getTypeName());
        $this->assertSame(array(), $descriptor->getParameters());
        $this->assertFalse($descriptor->hasParameters());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testQueryMustBeString()
    {
        new BindingDescriptor($this->uuid, 12345, 'vendor/type');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testQueryMustNotBeEmpty()
    {
        new BindingDescriptor($this->uuid, '', 'vendor/type');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTypeNameMustBeString()
    {
        new BindingDescriptor($this->uuid, '/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTypeNameMustNotBeEmpty()
    {
        new BindingDescriptor($this->uuid, '/path', '');
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\NoSuchParameterException
     */
    public function testGetParameterFailsIfUnknownParameter()
    {
        $descriptor = new BindingDescriptor($this->uuid, '/path', 'vendor/type');

        $descriptor->getParameter('foobar');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLanguageMustBeString()
    {
        new BindingDescriptor($this->uuid, '/path', 'vendor/type', array(), 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLanguageMustNotBeEmpty()
    {
        new BindingDescriptor($this->uuid, '/path', 'vendor/type', array(), '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterNameMustNotContainSpecialChars()
    {
        new BindingDescriptor($this->uuid, '/path', 'vendor/type', array('param=' => 'value'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterValueMustNotBeObject()
    {
        new BindingDescriptor($this->uuid, '/path', 'vendor/type', array('param' => new stdClass()));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterValueMustNotBeArray()
    {
        new BindingDescriptor($this->uuid, '/path', 'vendor/type', array('param' => array(1, 2, 3)));
    }

    public function testStaticCreate()
    {
        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));

        $this->assertNotNull($descriptor->getUuid());
        $this->assertSame('/path', $descriptor->getQuery());
        $this->assertSame('glob', $descriptor->getLanguage());
        $this->assertSame('vendor/type', $descriptor->getTypeName());
        $this->assertSame(array('param' => 'value'), $descriptor->getParameters());
    }

    public function testForPackageGeneratesDeterministicUuid()
    {
        $descriptor1 = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));
        $descriptor2 = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));

        $this->assertEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnQuery()
    {
        $descriptor1 = BindingDescriptor::create('/path1', 'vendor/type', array('param' => 'value'));
        $descriptor2 = BindingDescriptor::create('/path2', 'vendor/type', array('param' => 'value'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnType()
    {
        $descriptor1 = BindingDescriptor::create('/path', 'vendor/type1', array('param' => 'value'));
        $descriptor2 = BindingDescriptor::create('/path', 'vendor/type2', array('param' => 'value'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnParameterNames()
    {
        $descriptor1 = BindingDescriptor::create('/path', 'vendor/type', array('param1' => 'value'));
        $descriptor2 = BindingDescriptor::create('/path', 'vendor/type', array('param2' => 'value'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnParameterValues()
    {
        $descriptor1 = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value1'));
        $descriptor2 = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value2'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnParameterValueTypes()
    {
        $descriptor1 = BindingDescriptor::create('/path', 'vendor/type', array('param' => '1'));
        $descriptor2 = BindingDescriptor::create('/path', 'vendor/type', array('param' => 1));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnLanguage()
    {
        $descriptor1 = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'), 'language1');
        $descriptor2 = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'), 'language2');

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }
}
