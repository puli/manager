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
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingParameterDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Rhumsaa\Uuid\Uuid;
use stdClass;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingDescriptorTest extends PHPUnit_Framework_TestCase
{
    private $uuid;

    /**
     * @var Package
     */
    private $package;

    /**
     * @var Package
     */
    private $rootPackage;

    protected function setUp()
    {
        $this->uuid = Uuid::uuid4();
        $this->package = new Package(new PackageFile(), '/path', new InstallInfo('vendor/package', '/path'));
        $this->rootPackage = new RootPackage(new RootPackageFile(), '/root');
    }

    public function testCreate()
    {
        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));

        $this->assertNotNull($descriptor->getUuid());
        $this->assertSame('/path', $descriptor->getQuery());
        $this->assertSame('glob', $descriptor->getLanguage());
        $this->assertSame('vendor/type', $descriptor->getTypeName());
        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues());
        $this->assertSame('value', $descriptor->getParameterValue('param'));
        $this->assertTrue($descriptor->hasParameterValue('param'));
        $this->assertFalse($descriptor->hasParameterValue('foobar'));
        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testCreateWithCustomUuid()
    {
        $descriptor = new BindingDescriptor('/path', 'vendor/type', array(
            'param' => 'value',
        ), 'glob', $this->uuid);

        $this->assertSame($this->uuid, $descriptor->getUuid());
        $this->assertSame('/path', $descriptor->getQuery());
        $this->assertSame('glob', $descriptor->getLanguage());
        $this->assertSame('vendor/type', $descriptor->getTypeName());
        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testQueryMustBeString()
    {
        new BindingDescriptor(12345, 'vendor/type');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testQueryMustNotBeEmpty()
    {
        new BindingDescriptor('', 'vendor/type');
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
     * @expectedException \InvalidArgumentException
     */
    public function testLanguageMustBeString()
    {
        new BindingDescriptor('/path', 'vendor/type', array(), 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLanguageMustNotBeEmpty()
    {
        new BindingDescriptor('/path', 'vendor/type', array(), '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterNameMustNotContainSpecialChars()
    {
        new BindingDescriptor('/path', 'vendor/type', array('param=' => 'value'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterValueMustNotBeObject()
    {
        new BindingDescriptor('/path', 'vendor/type', array('param' => new stdClass()));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParameterValueMustNotBeArray()
    {
        new BindingDescriptor('/path', 'vendor/type', array('param' => array(1, 2, 3)));
    }

    public function testUuidDependsOnQuery()
    {
        $descriptor1 = new BindingDescriptor('/path1', 'vendor/type', array('param' => 'value'));
        $descriptor2 = new BindingDescriptor('/path2', 'vendor/type', array('param' => 'value'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnType()
    {
        $descriptor1 = new BindingDescriptor('/path', 'vendor/type1', array('param' => 'value'));
        $descriptor2 = new BindingDescriptor('/path', 'vendor/type2', array('param' => 'value'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnParameterNames()
    {
        $descriptor1 = new BindingDescriptor('/path', 'vendor/type', array('param1' => 'value'));
        $descriptor2 = new BindingDescriptor('/path', 'vendor/type', array('param2' => 'value'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnParameterValues()
    {
        $descriptor1 = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value1'));
        $descriptor2 = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value2'));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnParameterValueTypes()
    {
        $descriptor1 = new BindingDescriptor('/path', 'vendor/type', array('param' => '1'));
        $descriptor2 = new BindingDescriptor('/path', 'vendor/type', array('param' => 1));

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testUuidDependsOnLanguage()
    {
        $descriptor1 = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'), 'language1');
        $descriptor2 = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'), 'language2');

        $this->assertNotEquals($descriptor1->getUuid(), $descriptor2->getUuid());
    }

    public function testGetParameterValuesWhenUnloaded()
    {
        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));

        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues());
    }

    public function testGetParameterValuesWhenLoadedIncludesDefaultValues()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('foo', BindingParameterDescriptor::OPTIONAL, 'bar'),
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->load($this->package, $type);

        $this->assertSame(array('foo' => 'bar', 'param' => 'value'), $descriptor->getParameterValues());
    }

    public function testGetParameterValuesWhenLoadedDoesNotIncludeDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('foo', BindingParameterDescriptor::OPTIONAL, 'bar'),
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->load($this->package, $type);

        $this->assertSame(array('param' => 'value'), $descriptor->getParameterValues(false));
    }

    public function testGetParameterValueWhenUnloaded()
    {
        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));

        $this->assertSame('value', $descriptor->getParameterValue('param'));
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\NoSuchParameterException
     */
    public function testGetParameterValueWhenUnloadedThrowsExceptionIfNotFound()
    {
        $descriptor = new BindingDescriptor('/path', 'vendor/type');

        $descriptor->getParameterValue('foo');
    }

    public function testGetParameterValueWhenLoadedReturnsDefaultValue()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertSame('default', $descriptor->getParameterValue('param'));
    }

    public function testGetParameterValueWhenLoadedDoesNotReturnDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertNull($descriptor->getParameterValue('param', false));
    }

    public function testGetParameterValueWhenLoadedPrefersOverriddenValue()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->load($this->package, $type);

        $this->assertSame('value', $descriptor->getParameterValue('param'));
    }

    /**
     * @expectedException \Puli\Discovery\Api\Binding\NoSuchParameterException
     */
    public function testGetParameterValueWhenLoadedThrowsExceptionIfNotFound()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $descriptor->getParameterValue('foo');
    }

    public function testHasParameterValuesWhenUnloaded()
    {
        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));

        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testHasParameterValuesWhenLoadedIncludesDefaultValues()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testHasParameterValuesWhenLoadedDoesNotIncludeDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::OPTIONAL, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertFalse($descriptor->hasParameterValues(false));
    }

    public function testHasParameterValueWhenUnloaded()
    {
        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));

        $this->assertTrue($descriptor->hasParameterValue('param'));
        $this->assertFalse($descriptor->hasParameterValue('foo'));
    }

    public function testHasParameterValueWhenLoaded()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('default', BindingParameterDescriptor::OPTIONAL, 'value'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->load($this->package, $type);

        $this->assertTrue($descriptor->hasParameterValue('default'));
        $this->assertTrue($descriptor->hasParameterValue('param'));
        $this->assertFalse($descriptor->hasParameterValue('foo'));
    }

    public function testHasParameterValueWhenLoadedReturnsFalseForDefaultIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('default', BindingParameterDescriptor::OPTIONAL, 'value'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertFalse($descriptor->hasParameterValue('default', false));
    }

    public function testTypeNotFoundIfTypeIsNull()
    {
        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package);

        $this->assertSame(BindingState::TYPE_NOT_FOUND, $binding->getState());
    }

    public function testTypeNotFoundIfTypeIsNotLoaded()
    {
        $type = new BindingTypeDescriptor('vendor/type');

        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::TYPE_NOT_FOUND, $binding->getState());
    }

    public function testTypeNotEnabledIfTypeIsNotEnabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);
        $type->markDuplicate(true);

        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::TYPE_NOT_ENABLED, $binding->getState());
    }

    public function testInvalidIfInvalidParameter()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
        ));
        $type->load($this->package);

        // Parameter is missing
        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::INVALID, $binding->getState());
        $this->assertCount(1, $binding->getViolations());
    }

    public function testParametersNotValidatedIfTypeNotEnabled()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', BindingParameterDescriptor::REQUIRED),
        ));
        $type->load($this->package);
        $type->markDuplicate(true);

        // Parameter is missing
        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::TYPE_NOT_ENABLED, $binding->getState());
        $this->assertCount(0, $binding->getViolations());
    }

    public function testEnabledInRootPackage()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->rootPackage, $type);

        $this->assertSame(BindingState::ENABLED, $binding->getState());
    }

    public function testEnabledIfEnabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $this->package->getInstallInfo()->addEnabledBindingUuid($this->uuid);

        $binding = new BindingDescriptor('/path', 'vendor/type', array(), 'glob', $this->uuid);
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::ENABLED, $binding->getState());
    }

    public function testDisabledIfDisabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $this->package->getInstallInfo()->addDisabledBindingUuid($this->uuid);

        $binding = new BindingDescriptor('/path', 'vendor/type', array(), 'glob', $this->uuid);
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::DISABLED, $binding->getState());
    }

    public function testUndecidedIfNeitherEnabledNorDisabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $binding = new BindingDescriptor('/path', 'vendor/type', array(), 'glob', $this->uuid);
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::UNDECIDED, $binding->getState());
    }

    public function testCompare()
    {
        $binding1 = new BindingDescriptor('/vendor/a', 'vendor/a-type');
        $binding2 = new BindingDescriptor('/vendor/a', 'vendor/b-type');
        $binding3 = new BindingDescriptor('/vendor/b', 'vendor/a-type');

        $this->assertSame(0, BindingDescriptor::compare($binding1, $binding1));
        $this->assertSame(0, BindingDescriptor::compare($binding2, $binding2));
        $this->assertLessThan(0, BindingDescriptor::compare($binding1, $binding2));
        $this->assertGreaterThan(0, BindingDescriptor::compare($binding2, $binding1));
        $this->assertLessThan(0, BindingDescriptor::compare($binding1, $binding3));
        $this->assertGreaterThan(0, BindingDescriptor::compare($binding3, $binding1));
        $this->assertLessThan(0, BindingDescriptor::compare($binding2, $binding3));
        $this->assertGreaterThan(0, BindingDescriptor::compare($binding3, $binding2));
    }

    public function testMatch()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param'),
        ));
        $type->load($this->package);

        $uuid = Uuid::fromString('abcdb814-9dad-11d1-80b4-00c04fd430c8');
        $this->package->getInstallInfo()->addEnabledBindingUuid($uuid);
        $binding = new BindingDescriptor('/path', 'vendor/type', array(
            'param' => 'value',
        ), 'glob', $uuid);
        $binding->load($this->package, $type);

        $this->assertFalse($binding->match(Expr::same('foobar', BindingDescriptor::CONTAINING_PACKAGE)));
        $this->assertTrue($binding->match(Expr::same($this->package->getName(), BindingDescriptor::CONTAINING_PACKAGE)));

        $this->assertFalse($binding->match(Expr::same(BindingState::DISABLED, BindingDescriptor::STATE)));
        $this->assertTrue($binding->match(Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)));

        $this->assertFalse($binding->match(Expr::startsWith('abce', BindingDescriptor::UUID)));
        $this->assertTrue($binding->match(Expr::startsWith('abcd', BindingDescriptor::UUID)));

        $this->assertFalse($binding->match(Expr::same('/path/nested', BindingDescriptor::QUERY)));
        $this->assertTrue($binding->match(Expr::same('/path', BindingDescriptor::QUERY)));

        $this->assertFalse($binding->match(Expr::same('xpath', BindingDescriptor::LANGUAGE)));
        $this->assertTrue($binding->match(Expr::same('glob', BindingDescriptor::LANGUAGE)));

        $this->assertFalse($binding->match(Expr::same('vendor/other', BindingDescriptor::TYPE_NAME)));
        $this->assertTrue($binding->match(Expr::same('vendor/type', BindingDescriptor::TYPE_NAME)));

        $this->assertFalse($binding->match(Expr::key(BindingDescriptor::PARAMETER_VALUES, Expr::key('param', Expr::same('foobar')))));
        $this->assertTrue($binding->match(Expr::key(BindingDescriptor::PARAMETER_VALUES, Expr::key('param', Expr::same('value')))));
    }
}
