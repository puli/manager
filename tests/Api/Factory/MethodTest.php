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
use Puli\RepositoryManager\Api\Factory\FactoryClass;
use Puli\RepositoryManager\Api\Factory\Method;
use Puli\RepositoryManager\Api\Factory\Argument;
use Puli\RepositoryManager\Api\Factory\ReturnValue;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MethodTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var FactoryClass
     */
    private $class;

    /**
     * @var Method
     */
    private $method;

    protected function setUp()
    {
        $this->class = new FactoryClass('GeneratedClass', __DIR__, __DIR__, 'GeneratedClass.php');
        $this->method = new Method($this->class, 'doSomething');
    }

    public function testGetFactoryClass()
    {
        $this->assertSame($this->class, $this->method->getFactoryClass());
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
        new Method($this->class, null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameEmpty()
    {
        new Method($this->class, '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFailsIfNameNoString()
    {
        new Method($this->class, 1234);
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
        $this->method->addArgument($arg1 = new Argument('arg1', null, 'string', 'The description'));
        $this->method->addArgument($arg2 = new Argument('arg2', null, 'int', 'The description'));

        $this->assertSame(array(
            'arg1' => $arg1,
            'arg2' => $arg2,
        ), $this->method->getArguments());
    }

    public function testAddArguments()
    {
        $this->method->addArgument($arg1 = new Argument('arg1', null, 'string', 'The description'));
        $this->method->addArguments(array(
            $arg2 = new Argument('arg2', null, 'int', 'The description'),
            $arg3 = new Argument('arg3', null, 'float', 'The description'),
        ));

        $this->assertSame(array(
            'arg1' => $arg1,
            'arg2' => $arg2,
            'arg3' => $arg3,
        ), $this->method->getArguments());
    }

    public function testSetArguments()
    {
        $this->method->addArgument($arg1 = new Argument('arg1', null, 'string', 'The description'));
        $this->method->setArguments(array(
            $arg2 = new Argument('arg2', null, 'int', 'The description'),
            $arg3 = new Argument('arg3', null, 'float', 'The description'),
        ));

        $this->assertSame(array(
            'arg2' => $arg2,
            'arg3' => $arg3,
        ), $this->method->getArguments());
    }

    public function testRemoveArgument()
    {
        $this->method->addArgument($arg1 = new Argument('arg1', null, 'string', 'The description'));
        $this->method->addArgument($arg2 = new Argument('arg2', null, 'int', 'The description'));
        $this->method->removeArgument('arg1');

        $this->assertSame(array(
            'arg2' => $arg2,
        ), $this->method->getArguments());
    }

    public function testRemoveArgumentIgnoresUnknownArguments()
    {
        $this->method->addArgument($arg1 = new Argument('arg1', null, 'string', 'The description'));
        $this->method->addArgument($arg2 = new Argument('arg2', null, 'int', 'The description'));

        $this->method->removeArgument('foobar');

        $this->assertSame(array(
            'arg1' => $arg1,
            'arg2' => $arg2,
        ), $this->method->getArguments());
    }

    public function testClearArgument()
    {
        $this->method->addArgument($arg1 = new Argument('arg1', null, 'string', 'The description'));
        $this->method->addArgument($arg2 = new Argument('arg2', null, 'int', 'The description'));
        $this->method->clearArguments();

        $this->assertSame(array(), $this->method->getArguments());
    }

    public function testGetArgument()
    {
        $this->method->addArgument($arg1 = new Argument('arg1', null, 'string', 'The description'));
        $this->method->addArgument($arg2 = new Argument('arg2', null, 'int', 'The description'));

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

        $this->method->addArgument(new Argument('arg', null, 'string', 'The description'));

        $this->assertTrue($this->method->hasArgument('arg'));
    }

    public function testHasArguments()
    {
        $this->assertFalse($this->method->hasArguments());

        $this->method->addArgument(new Argument('arg', null, 'string', 'The description'));

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

    public function testSetSourceCode()
    {
        $this->method->setSourceCode("\$foo = 'bar';");

        $this->assertSame("\$foo = 'bar';", $this->method->getSourceCode());
    }

    public function testSetSourceCodeTrims()
    {
        $this->method->setSourceCode("    \$foo = 'bar';\n\n");

        $this->assertSame("\$foo = 'bar';", $this->method->getSourceCode());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetSourceCodeFailsIfNull()
    {
        $this->method->setSourceCode(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetSourceCodeFailsIfEmpty()
    {
        $this->method->setSourceCode('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetSourceCodeFailsIfNoString()
    {
        $this->method->setSourceCode(1234);
    }

    public function testAddSourceCode()
    {
        $this->method->setSourceCode("\$foo = 'bar';");
        $this->method->addSourceCode("\$baz = \$foo;");

        $expected = <<<EOF
\$foo = 'bar';
\$baz = \$foo;
EOF;

        $this->assertSame($expected, $this->method->getSourceCode());
    }

    public function testAddSourceCodeTrims()
    {
        $this->method->setSourceCode("\$foo = 'bar';");
        $this->method->addSourceCode("    \$baz = \$foo;\n\n");

        $expected = <<<EOF
\$foo = 'bar';
\$baz = \$foo;
EOF;

        $this->assertSame($expected, $this->method->getSourceCode());
    }

    public function testAddSourceCodeWithoutPriorSet()
    {
        $this->method->addSourceCode("\$baz = \$foo;");

        $expected = <<<EOF
\$baz = \$foo;
EOF;

        $this->assertSame($expected, $this->method->getSourceCode());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddSourceCodeFailsIfNull()
    {
        $this->method->addSourceCode(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddSourceCodeFailsIfEmpty()
    {
        $this->method->addSourceCode('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddSourceCodeFailsIfNoString()
    {
        $this->method->addSourceCode(1234);
    }

    public function testClearSourceCode()
    {
        $this->method->setSourceCode("\$foo = 'bar';");
        $this->method->clearSourceCode();

        $this->assertSame('', $this->method->getSourceCode());
    }
}
