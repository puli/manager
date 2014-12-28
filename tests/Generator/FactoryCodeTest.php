<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Generator\FactoryCode;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FactoryCodeTest extends PHPUnit_Framework_TestCase
{
    public function testSortImports()
    {
        $code = new FactoryCode();
        $code->addImport('C');
        $code->addImport('A');
        $code->addImport('B');

        $this->assertSame(array('A', 'B', 'C'), $code->getImports());
    }

    public function testRemoveDuplicateImports()
    {
        $code = new FactoryCode();
        $code->addImport('B');
        $code->addImport('A');
        $code->addImport('B');

        $this->assertSame(array('A', 'B'), $code->getImports());
    }

    public function testAddImports()
    {
        $code = new FactoryCode();
        $code->addImports(array('A', 'B'));

        $this->assertSame(array('A', 'B'), $code->getImports());
    }

    public function testAddVarDeclaration()
    {
        $code = new FactoryCode();
        $code->addVarDeclaration('$foo', '$foo = "Foo";');

        $this->assertSame(array(
            '$foo' => '$foo = "Foo";',
        ), $code->getVarDeclarations());
    }

    public function testAddVarDeclarations()
    {
        $code = new FactoryCode();
        $code->addVarDeclarations(array(
            '$foo' => '$foo = "Foo";',
            '$bar' => '$bar = "Bar";',
        ));

        $this->assertSame(array(
            '$foo' => '$foo = "Foo";',
            '$bar' => '$bar = "Bar";',
        ), $code->getVarDeclarations());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddVarDeclarationFailsIfVarNameAlreadyUsed()
    {
        $code = new FactoryCode();
        $code->addVarDeclaration('$foo', '$foo = "Foo";');
        $code->addVarDeclaration('$foo', '$foo = "Bar";');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testVariablesMustStartWithDollar()
    {
        $code = new FactoryCode();
        $code->addVarDeclaration('foo', '$foo = "Foo";');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testVariablesMustOccurInCode()
    {
        $code = new FactoryCode();
        $code->addVarDeclaration('$foo', '$bar = "Foo";');
    }
}
