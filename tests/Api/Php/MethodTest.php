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
use Puli\Manager\Api\Php\Argument;
use Puli\Manager\Api\Php\Clazz;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Api\Php\ReturnValue;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MethodTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Clazz
     */
    private $class;

    /**
     * @var Method
     */
    private $method;

    protected function setUp()
    {
        $this->class = new Clazz('GeneratedClass');
        $this->method = new Method('doSomething');
    }

    public function testGetName()
    {
        $this->assertSame('doSomething', $this->method->getName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameNull()
    {
        new Method(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameEmpty()
    {
        new Method('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameNoString()
    {
        new Method(1234);
    }

    public function testSetFactoryClass()
    {
        $this->method->setClass($this->class);

        $this->assertSame($this->class, $this->method->getClass());
    }

    public function testSetDescription()
    {
        $this->method->setDescription('The description');

        $this->assertSame('The description', $this->method->getDescription());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNull()
    {
        $this->method->setDescription(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfEmpty()
    {
        $this->method->setDescription('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNoString()
    {
        $this->method->setDescription(1234);
    }

    public function testAddArgument()
    {
        $this->method->addArgument($arg1 = new Argument('arg1'));
        $this->method->addArgument($arg2 = new Argument('arg2'));

        $this->assertSame(array(
            'arg1' => $arg1,
            'arg2' => $arg2,
        ), $this->method->getArguments());
    }

    public function testAddArguments()
    {
        $this->method->addArgument($arg1 = new Argument('arg1'));
        $this->method->addArguments(array(
            $arg2 = new Argument('arg2'),
            $arg3 = new Argument('arg3'),
        ));

        $this->assertSame(array(
            'arg1' => $arg1,
            'arg2' => $arg2,
            'arg3' => $arg3,
        ), $this->method->getArguments());
    }

    public function testSetArguments()
    {
        $this->method->addArgument($arg1 = new Argument('arg1'));
        $this->method->setArguments(array(
            $arg2 = new Argument('arg2'),
            $arg3 = new Argument('arg3'),
        ));

        $this->assertSame(array(
            'arg2' => $arg2,
            'arg3' => $arg3,
        ), $this->method->getArguments());
    }

    public function testRemoveArgument()
    {
        $this->method->addArgument($arg1 = new Argument('arg1'));
        $this->method->addArgument($arg2 = new Argument('arg2'));
        $this->method->removeArgument('arg1');

        $this->assertSame(array(
            'arg2' => $arg2,
        ), $this->method->getArguments());
    }

    public function testRemoveArgumentIgnoresUnknownArguments()
    {
        $this->method->addArgument($arg1 = new Argument('arg1'));
        $this->method->addArgument($arg2 = new Argument('arg2'));

        $this->method->removeArgument('foobar');

        $this->assertSame(array(
            'arg1' => $arg1,
            'arg2' => $arg2,
        ), $this->method->getArguments());
    }

    public function testClearArgument()
    {
        $this->method->addArgument($arg1 = new Argument('arg1'));
        $this->method->addArgument($arg2 = new Argument('arg2'));
        $this->method->clearArguments();

        $this->assertSame(array(), $this->method->getArguments());
    }

    public function testGetArgument()
    {
        $this->method->addArgument($arg1 = new Argument('arg1'));
        $this->method->addArgument($arg2 = new Argument('arg2'));

        $this->assertSame($arg1, $this->method->getArgument('arg1'));
        $this->assertSame($arg2, $this->method->getArgument('arg2'));
    }

    /**
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage foobar
     */
    public function testGetArgumentFailsIfNotFound()
    {
        $this->method->getArgument('foobar');
    }

    public function testHasArgument()
    {
        $this->assertFalse($this->method->hasArgument('arg'));

        $this->method->addArgument(new Argument('arg'));

        $this->assertTrue($this->method->hasArgument('arg'));
    }

    public function testHasArguments()
    {
        $this->assertFalse($this->method->hasArguments());

        $this->method->addArgument(new Argument('arg'));

        $this->assertTrue($this->method->hasArguments());
    }

    public function testSetReturnValue()
    {
        $returnValue = new ReturnValue('42', 'int', 'The description');

        $this->method->setReturnValue($returnValue);

        $this->assertSame($returnValue, $this->method->getReturnValue());
    }

    public function testRemoveReturnValue()
    {
        $this->method->setReturnValue(new ReturnValue('42', 'int', 'The description'));
        $this->method->removeReturnValue();

        $this->assertNull($this->method->getReturnValue());
    }

    public function testHasReturnValue()
    {
        $this->assertFalse($this->method->hasReturnValue());

        $this->method->setReturnValue(new ReturnValue('42', 'int', 'The description'));

        $this->assertTrue($this->method->hasReturnValue());

        $this->method->removeReturnValue();

        $this->assertFalse($this->method->hasReturnValue());
    }

    public function testSetBody()
    {
        $this->method->setBody("\$foo = 'bar';");

        $this->assertSame("\$foo = 'bar';", $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetBodyFailsIfNull()
    {
        $this->method->setBody(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetBodyFailsIfEmpty()
    {
        $this->method->setBody('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetBodyFailsIfNoString()
    {
        $this->method->setBody(1234);
    }

    public function testAddBody()
    {
        $this->method->setBody("\$foo = 'bar';");
        $this->method->addBody('$baz = $foo;');

        $expected = <<<'EOF'
$foo = 'bar';
$baz = $foo;
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testAddBodyWithoutPriorSet()
    {
        $this->method->addBody('$baz = $foo;');

        $expected = <<<'EOF'
$baz = $foo;
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddBodyFailsIfNull()
    {
        $this->method->addBody(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddBodyFailsIfEmpty()
    {
        $this->method->addBody('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddBodyFailsIfNoString()
    {
        $this->method->addBody(1234);
    }

    public function testClearBody()
    {
        $this->method->setBody("\$foo = 'bar';");
        $this->method->clearBody();

        $this->assertSame('', $this->method->getBody());
    }
}
