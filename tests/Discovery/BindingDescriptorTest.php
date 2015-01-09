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
use Puli\RepositoryManager\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Discovery\Store\BindingTypeStore;
use Puli\RepositoryManager\Package\InstallInfo;
use Puli\RepositoryManager\Package\Package;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Rhumsaa\Uuid\Uuid;
use stdClass;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorTest extends PHPUnit_Framework_TestCase
{
    private $uuid;

    /**
     * @var BindingTypeStore
     */
    private $typeStore;

    /**
     * @var Package
     */
    private $package;

    protected function setUp()
    {
        $this->uuid = Uuid::uuid4();
        $this->typeStore = new BindingTypeStore();
        $this->package = new Package(new PackageFile(), '/path', new InstallInfo('vendor/package', '/path'));
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
        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues());
        $this->assertSame('value', $descriptor->getParameterValue('param'));
        $this->assertTrue($descriptor->hasParameterValue('param'));
        $this->assertFalse($descriptor->hasParameterValue('foobar'));
        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testCreateWithQueryLanguage()
    {
        $descriptor = new BindingDescriptor($this->uuid, '/path', 'vendor/type', array(), 'xpath');

        $this->assertSame($this->uuid, $descriptor->getUuid());
        $this->assertSame('/path', $descriptor->getQuery());
        $this->assertSame('xpath', $descriptor->getLanguage());
        $this->assertSame('vendor/type', $descriptor->getTypeName());
        $this->assertSame(array(), $descriptor->getParameterValues());
        $this->assertFalse($descriptor->hasParameterValues());
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
        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues());
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

    public function testGetParameterValuesWhenUnloaded()
    {
        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));

        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues());
    }

    public function testGetParameterValuesWhenLoadedIncludesDefaultValues()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('foo', false, 'bar'),
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertSame(array('foo' => 'bar', 'param' => 'value'), $descriptor->getParameterValues());
    }

    public function testGetParameterValuesWhenLoadedDoesNotIncludeDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('foo', false, 'bar'),
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues(false));
    }

    public function testGetParameterValueWhenUnloaded()
    {
        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));

        $this->assertSame('value', $descriptor->getParameterValue('param'));
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\NoSuchParameterException
     */
    public function testGetParameterValueWhenUnloadedThrowsExceptionIfNotFound()
    {
        $descriptor = BindingDescriptor::create('/path', 'vendor/type');

        $descriptor->getParameterValue('foo');
    }

    public function testGetParameterValueWhenLoadedReturnsDefaultValue()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type');
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertSame('default', $descriptor->getParameterValue('param'));
    }

    public function testGetParameterValueWhenLoadedDoesNotReturnDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type');
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertNull($descriptor->getParameterValue('param', false));
    }

    public function testGetParameterValueWhenLoadedPrefersOverriddenValue()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertSame('value', $descriptor->getParameterValue('param'));
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\NoSuchParameterException
     */
    public function testGetParameterValueWhenLoadedThrowsExceptionIfNotFound()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type');
        $descriptor->refreshState($this->package, $this->typeStore);

        $descriptor->getParameterValue('foo');
    }

    public function testHasParameterValuesWhenUnloaded()
    {
        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));

        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testHasParameterValuesWhenLoadedIncludesDefaultValues()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type');
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testHasParameterValuesWhenLoadedDoesNotIncludeDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type');
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertFalse($descriptor->hasParameterValues(false));
    }

    public function testHasParameterValueWhenUnloaded()
    {
        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));

        $this->assertTrue($descriptor->hasParameterValue('param'));
        $this->assertFalse($descriptor->hasParameterValue('foo'));
    }

    public function testHasParameterValueWhenLoaded()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('default', false, 'value'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertTrue($descriptor->hasParameterValue('default'));
        $this->assertTrue($descriptor->hasParameterValue('param'));
        $this->assertFalse($descriptor->hasParameterValue('foo'));
    }

    public function testHasParameterValueWhenLoadedReturnsFalseForDefaultIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('default', false, 'value'),
        ));
        $this->typeStore->add($type, $this->package);
        $type->refreshState($this->typeStore);

        $descriptor = BindingDescriptor::create('/path', 'vendor/type');
        $descriptor->refreshState($this->package, $this->typeStore);

        $this->assertFalse($descriptor->hasParameterValue('default', false));
    }
}
