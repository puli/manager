<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Installer;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerParameter;
use stdClass;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallerDescriptorTest extends PHPUnit_Framework_TestCase
{
    const SYMLINK_CLASS = 'Puli\Manager\Tests\Api\Installer\Fixtures\TestSymlinkInstaller';

    public function testCreate()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS);

        $this->assertSame('symlink', $descriptor->getName());
        $this->assertSame(self::SYMLINK_CLASS, $descriptor->getClassName());
        $this->assertNull($descriptor->getDescription());
        $this->assertSame(array(), $descriptor->getParameters());
    }

    public function testCreateWithDescription()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, 'The description');

        $this->assertSame('symlink', $descriptor->getName());
        $this->assertSame(self::SYMLINK_CLASS, $descriptor->getClassName());
        $this->assertSame('The description', $descriptor->getDescription());
        $this->assertSame(array(), $descriptor->getParameters());
    }

    public function testCreateWithParameters()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            $param1 = new InstallerParameter('param1'),
            $param2 = new InstallerParameter('param2'),
        ));

        $this->assertSame('symlink', $descriptor->getName());
        $this->assertSame(self::SYMLINK_CLASS, $descriptor->getClassName());
        $this->assertNull($descriptor->getDescription());
        $this->assertSame(array(
            'param1' => $param1,
            'param2' => $param2,
        ), $descriptor->getParameters());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNull()
    {
        new InstallerDescriptor(null, self::SYMLINK_CLASS);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameEmpty()
    {
        new InstallerDescriptor('', self::SYMLINK_CLASS);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNoString()
    {
        new InstallerDescriptor(1234, self::SYMLINK_CLASS);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfClassNull()
    {
        new InstallerDescriptor('symlink', null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfClassEmpty()
    {
        new InstallerDescriptor('symlink', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfClassNoString()
    {
        new InstallerDescriptor('symlink', 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfDescriptionEmpty()
    {
        new InstallerDescriptor('symlink', self::SYMLINK_CLASS, '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfDescriptionNoString()
    {
        new InstallerDescriptor('symlink', self::SYMLINK_CLASS, 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInvalidParameter()
    {
        new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(new stdClass()));
    }

    public function testGetParameter()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            $param = new InstallerParameter('param'),
        ));

        $this->assertSame($param, $descriptor->getParameter('param'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchParameterException
     * @expectedExceptionMessage foobar
     */
    public function testGetParameterFailsIfNotFound()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS);

        $descriptor->getParameter('foobar');
    }

    public function testHasParameter()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param'),
        ));

        $this->assertTrue($descriptor->hasParameter('param'));
        $this->assertFalse($descriptor->hasParameter('foobar'));
    }

    public function testHasParameters()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param'),
        ));

        $this->assertTrue($descriptor->hasParameters());
    }

    public function testHasNoParameters()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS);

        $this->assertFalse($descriptor->hasParameters());
    }

    public function testHasRequiredParameters()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param', InstallerParameter::REQUIRED),
        ));

        $this->assertTrue($descriptor->hasRequiredParameters());
    }

    public function testHasNoRequiredParameters()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param', InstallerParameter::OPTIONAL),
        ));

        $this->assertFalse($descriptor->hasRequiredParameters());
    }

    public function testHasOptionalParameters()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param', InstallerParameter::OPTIONAL),
        ));

        $this->assertTrue($descriptor->hasOptionalParameters());
    }

    public function testHasNoOptionalParameters()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param', InstallerParameter::REQUIRED),
        ));

        $this->assertFalse($descriptor->hasOptionalParameters());
    }

    public function testGetParameterValues()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));

        $this->assertSame(array(
            'param2' => 'default1',
            'param3' => 'default2',
        ), $descriptor->getParameterValues());
    }

    public function testGetNoParameterValues()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::REQUIRED),
        ));

        $this->assertSame(array(), $descriptor->getParameterValues());
    }

    public function testGetParameterValue()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));

        $this->assertNull($descriptor->getParameterValue('param1'));
        $this->assertSame('default1', $descriptor->getParameterValue('param2'));
        $this->assertSame('default2', $descriptor->getParameterValue('param3'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchParameterException
     * @expectedExceptionMessage foobar
     */
    public function testGetParameterValueFailsIfNotFound()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS);

        $descriptor->getParameterValue('foobar');
    }

    public function testHasParameterValues()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));

        $this->assertTrue($descriptor->hasParameterValues());
    }

    public function testHasNoParameterValues()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::REQUIRED),
        ));

        $this->assertFalse($descriptor->hasParameterValues());
    }

    public function testHasParameterValue()
    {
        $descriptor = new InstallerDescriptor('symlink', self::SYMLINK_CLASS, null, array(
            new InstallerParameter('param1', InstallerParameter::REQUIRED),
            new InstallerParameter('param2', InstallerParameter::OPTIONAL, 'default1'),
            new InstallerParameter('param3', InstallerParameter::OPTIONAL, 'default2'),
        ));

        $this->assertFalse($descriptor->hasParameterValue('param1'));
        $this->assertTrue($descriptor->hasParameterValue('param2'));
        $this->assertTrue($descriptor->hasParameterValue('param3'));
        $this->assertFalse($descriptor->hasParameterValue('foobar'));
    }
}
