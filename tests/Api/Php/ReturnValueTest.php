<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Php;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Php\ReturnValue;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReturnValueTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $this->assertSame('12', $returnValue->getValue());
        $this->assertSame('int', $returnValue->getType());
        $this->assertSame('The description', $returnValue->getDescription());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfValueNull()
    {
        new ReturnValue(null, 'int', 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfValueEmpty()
    {
        new ReturnValue('', 'int', 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfValueNoString()
    {
        new ReturnValue(1234, 'int', 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfTypeNull()
    {
        new ReturnValue('42', null, 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfTypeEmpty()
    {
        new ReturnValue('42', '', 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfTypeNoString()
    {
        new ReturnValue('42', 1234, 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfDescriptionEmpty()
    {
        new ReturnValue('42', 'int', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfDescriptionNoString()
    {
        new ReturnValue('42', 'int', 1234);
    }
}
