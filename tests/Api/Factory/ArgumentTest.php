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
use Puli\RepositoryManager\Api\Factory\Argument;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArgumentTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $argument = new Argument('argument', null, 'int', 'The description');

        $this->assertSame('argument', $argument->getName());
        $this->assertNull($argument->getTypeHint());
        $this->assertSame('int', $argument->getType());
        $this->assertSame('The description', $argument->getDescription());
    }

    public function testCreateWithTypeHint()
    {
        $argument = new Argument('argument', 'stdClass', 'stdClass', 'The description');

        $this->assertSame('stdClass', $argument->getTypeHint());
        $this->assertSame('stdClass', $argument->getType());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameNull()
    {
        new Argument(null, null, 'int', 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameEmpty()
    {
        new Argument('', null, 'int', 'The description');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameNoString()
    {
        new Argument(1234, null, 'int', 'The description');
    }

    public function testSetTypeHint()
    {
        $argument = new Argument('argument', null, 'int', 'The description');

        $argument->setTypeHint('stdClass');

        $this->assertSame('stdClass', $argument->getTypeHint());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeHintFailsIfNull()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setTypeHint(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeHintFailsIfEmpty()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setTypeHint('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeHintFailsIfNoString()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setTypeHint(1234);
    }

    public function testRemoveTypeHint()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->removeTypeHint();

        $this->assertNull($argument->getTypeHint());
    }

    public function testHasTypeHint()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $this->assertTrue($argument->hasTypeHint());

        $argument->removeTypeHint();

        $this->assertFalse($argument->hasTypeHint());
    }

    public function testSetType()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setType('string');

        $this->assertSame('string', $argument->getType());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeFailsIfNull()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setType(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeFailsIfEmpty()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setType('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeFailsIfNoString()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setType(1234);
    }

    public function testSetDescription()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setDescription('New description');

        $this->assertSame('New description', $argument->getDescription());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNull()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setDescription(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfEmpty()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setDescription('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNoString()
    {
        $argument = new Argument('argument', 'stdClass', 'int', 'The description');

        $argument->setDescription(1234);
    }
}
