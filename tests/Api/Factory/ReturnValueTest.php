<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Api\Factory;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Factory\ReturnValue;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReturnValueTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $this->assertSame('12', $returnValue->getSourceCode());
        $this->assertSame('int', $returnValue->getType());
        $this->assertSame('The description', $returnValue->getDescription());
    }

    public function testSetSourceCode()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setSourceCode('"foobar"');

        $this->assertSame('"foobar"', $returnValue->getSourceCode());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetSourceCodeFailsIfNull()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setSourceCode(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetSourceCodeFailsIfEmpty()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setSourceCode('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetSourceCodeFailsIfNoString()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setSourceCode(1234);
    }

    public function testSetType()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setType('stdClass');

        $this->assertSame('stdClass', $returnValue->getType());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeFailsIfNull()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setType(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeFailsIfEmpty()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setType('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeFailsIfNoString()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setType(1234);
    }

    public function testSetDescription()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setDescription('New description');

        $this->assertSame('New description', $returnValue->getDescription());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNull()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setDescription(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfEmpty()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setDescription('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNoString()
    {
        $returnValue = new ReturnValue('12', 'int', 'The description');

        $returnValue->setDescription(1234);
    }
}
