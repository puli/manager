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
use Puli\Manager\Api\Installer\InstallerParameter;
use stdClass;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallerParameterTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $parameter = new InstallerParameter('param');

        $this->assertSame('param', $parameter->getName());
        $this->assertFalse($parameter->isRequired());
        $this->assertNull($parameter->getDefaultValue());
        $this->assertNull($parameter->getDescription());
    }

    public function testCreateRequired()
    {
        $parameter = new InstallerParameter('param', InstallerParameter::REQUIRED);

        $this->assertSame('param', $parameter->getName());
        $this->assertTrue($parameter->isRequired());
        $this->assertNull($parameter->getDefaultValue());
        $this->assertNull($parameter->getDescription());
    }

    public function testCreateWithDefaultValue()
    {
        $parameter = new InstallerParameter('param', InstallerParameter::OPTIONAL, 'default');

        $this->assertSame('param', $parameter->getName());
        $this->assertFalse($parameter->isRequired());
        $this->assertSame('default', $parameter->getDefaultValue());
        $this->assertNull($parameter->getDescription());
    }

    public function testCreateWithDescription()
    {
        $parameter = new InstallerParameter('param', InstallerParameter::OPTIONAL, null, 'The description');

        $this->assertSame('param', $parameter->getName());
        $this->assertFalse($parameter->isRequired());
        $this->assertNull($parameter->getDefaultValue());
        $this->assertSame('The description', $parameter->getDescription());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFailIfRequiredAndDefaultValue()
    {
        new InstallerParameter('param', InstallerParameter::REQUIRED, 'default');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNull()
    {
        new InstallerParameter(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameEmpty()
    {
        new InstallerParameter('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNameNoString()
    {
        new InstallerParameter(1234);
    }

    public function testAcceptFlagsNull()
    {
        $parameter = new InstallerParameter('param', null);

        $this->assertSame(0, $parameter->getFlags());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFlagsNoInt()
    {
        new InstallerParameter('param', 'foobar');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfDefaultValueNoScalar()
    {
        new InstallerParameter('param', InstallerParameter::OPTIONAL, new stdClass());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfDescriptionEmpty()
    {
        new InstallerParameter('param', InstallerParameter::OPTIONAL, null, '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfDescriptionNoString()
    {
        new InstallerParameter('param', InstallerParameter::OPTIONAL, null, 1234);
    }
}
