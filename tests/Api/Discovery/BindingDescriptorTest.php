<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Api\Discovery;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingParameterDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\BindingTypeDescriptor;
use Puli\RepositoryManager\Api\Package\InstallInfo;
use Puli\RepositoryManager\Api\Package\Package;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\RootPackage;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Rhumsaa\Uuid\Uuid;
use stdClass;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
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

    public function testCreateGeneratesDeterministicUuid()
    {
        $descriptor1 = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));
        $descriptor2 = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));

        $this->assertEquals($descriptor1->getUuid(), $descriptor2->getUuid());
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
            new BindingParameterDescriptor('foo', false, 'bar'),
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type', array('param' => 'value'));
        $descriptor->load($this->package, $type);

        $this->assertSame(array('foo' => 'bar', 'param' => 'value'), $descriptor->getParameterValues());
    }

    public function testGetParameterValuesWhenLoadedDoesNotIncludeDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('foo', false, 'bar'),
            new BindingParameterDescriptor('param', false, 'default'),
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
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertSame('default', $descriptor->getParameterValue('param'));
    }

    public function testGetParameterValueWhenLoadedDoesNotReturnDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertNull($descriptor->getParameterValue('param', false));
    }

    public function testGetParameterValueWhenLoadedPrefersOverriddenValue()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
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
            new BindingParameterDescriptor('param', false, 'default'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testHasParameterValuesWhenLoadedDoesNotIncludeDefaultValuesIfSuppressed()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', false, 'default'),
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
            new BindingParameterDescriptor('default', false, 'value'),
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
            new BindingParameterDescriptor('default', false, 'value'),
        ));
        $type->load($this->package);

        $descriptor = new BindingDescriptor('/path', 'vendor/type');
        $descriptor->load($this->package, $type);

        $this->assertFalse($descriptor->hasParameterValue('default', false));
    }

    public function testHeldBackIfTypeIsNull()
    {
        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package);

        $this->assertSame(BindingState::HELD_BACK, $binding->getState());
    }

    public function testHeldBackIfTypeIsNotLoaded()
    {
        $type = new BindingTypeDescriptor('vendor/type');

        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::HELD_BACK, $binding->getState());
    }

    public function testHeldBackIfTypeIsNotEnabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);
        $type->markDuplicate(true);

        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::HELD_BACK, $binding->getState());
    }

    public function testInvalidIfInvalidParameter()
    {
        $type = new BindingTypeDescriptor('vendor/type', null, array(
            new BindingParameterDescriptor('param', true),
        ));
        $type->load($this->package);

        // Parameter is missing
        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->package, $type);

        $this->assertSame(BindingState::INVALID, $binding->getState());
        $this->assertCount(1, $binding->getViolations());
    }

    public function testEnabledInRootPackage()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->rootPackage, $type);

        $this->assertSame(BindingState::ENABLED, $binding->getState());
    }

    public function testOverriddenIfMarkedOverriddenInRootPackage()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $binding = new BindingDescriptor('/path', 'vendor/type');
        $binding->load($this->rootPackage, $type);
        $binding->markOverridden(true);

        $this->assertSame(BindingState::OVERRIDDEN, $binding->getState());
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

    public function testOverriddenIfMarkedOverriddenAndEnabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $this->package->getInstallInfo()->addEnabledBindingUuid($this->uuid);

        $binding = new BindingDescriptor('/path', 'vendor/type', array(), 'glob', $this->uuid);
        $binding->load($this->package, $type);
        $binding->markOverridden(true);

        $this->assertSame(BindingState::OVERRIDDEN, $binding->getState());
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

    public function testDisabledIfMarkedOverriddenAndDisabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $this->package->getInstallInfo()->addDisabledBindingUuid($this->uuid);

        $binding = new BindingDescriptor('/path', 'vendor/type', array(), 'glob', $this->uuid);
        $binding->load($this->package, $type);
        $binding->markOverridden(true);

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

    public function testUndecidedIfMarkedOverriddenAndNeitherEnabledNorDisabled()
    {
        $type = new BindingTypeDescriptor('vendor/type');
        $type->load($this->package);

        $binding = new BindingDescriptor('/path', 'vendor/type', array(), 'glob', $this->uuid);
        $binding->load($this->package, $type);
        $binding->markOverridden(true);

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

        $this->assertFalse($binding->match(Expr::same(BindingDescriptor::CONTAINING_PACKAGE, 'foobar')));
        $this->assertTrue($binding->match(Expr::same(BindingDescriptor::CONTAINING_PACKAGE, $this->package->getName())));

        $this->assertFalse($binding->match(Expr::same(BindingDescriptor::STATE, BindingState::DISABLED)));
        $this->assertTrue($binding->match(Expr::same(BindingDescriptor::STATE, BindingState::ENABLED)));

        $this->assertFalse($binding->match(Expr::startsWith(BindingDescriptor::UUID, 'abce')));
        $this->assertTrue($binding->match(Expr::startsWith(BindingDescriptor::UUID, 'abcd')));

        $this->assertFalse($binding->match(Expr::same(BindingDescriptor::QUERY, '/path/nested')));
        $this->assertTrue($binding->match(Expr::same(BindingDescriptor::QUERY, '/path')));

        $this->assertFalse($binding->match(Expr::same(BindingDescriptor::LANGUAGE, 'xpath')));
        $this->assertTrue($binding->match(Expr::same(BindingDescriptor::LANGUAGE, 'glob')));

        $this->assertFalse($binding->match(Expr::same(BindingDescriptor::TYPE_NAME, 'vendor/other')));
        $this->assertTrue($binding->match(Expr::same(BindingDescriptor::TYPE_NAME, 'vendor/type')));

        $this->assertFalse($binding->match(Expr::keySame(BindingDescriptor::PARAMETER_VALUES, 'param', 'foobar')));
        $this->assertTrue($binding->match(Expr::keySame(BindingDescriptor::PARAMETER_VALUES, 'param', 'value')));
    }
}
