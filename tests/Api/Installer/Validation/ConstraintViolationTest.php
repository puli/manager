<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Installer\Validation;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Installer\Validation\ConstraintViolation;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConstraintViolationTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $violation = new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, 'value', 'symlink');

        $this->assertSame(ConstraintViolation::MISSING_PARAMETER, $violation->getCode());
        $this->assertSame('value', $violation->getInvalidValue());
        $this->assertSame('symlink', $violation->getInstallerName());
        $this->assertNull($violation->getParameterName());
    }

    public function testCreateWithParameterName()
    {
        $violation = new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, 'value', 'symlink', 'param');

        $this->assertSame(ConstraintViolation::MISSING_PARAMETER, $violation->getCode());
        $this->assertSame('value', $violation->getInvalidValue());
        $this->assertSame('symlink', $violation->getInstallerName());
        $this->assertSame('param', $violation->getParameterName());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage 123
     */
    public function testFailIfInvalidViolationCode()
    {
        new ConstraintViolation(123, 'value', 'symlink');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallerNameNull()
    {
        new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, 'value', null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallerNameEmpty()
    {
        new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, 'value', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfInstallerNameNoString()
    {
        new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, 'value', 1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfParameterNameEmpty()
    {
        new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, 'value', 'symlink', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfParameterNameNoString()
    {
        new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, 'value', 'symlink', 1234);
    }
}
