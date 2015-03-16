<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Factory;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Factory\BuildRecipe;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BuildRecipeTest extends PHPUnit_Framework_TestCase
{
    public function testSortImports()
    {
        $recipe = new BuildRecipe();
        $recipe->addImport('C');
        $recipe->addImport('A');
        $recipe->addImport('B');

        $this->assertSame(array('A', 'B', 'C'), $recipe->getImports());
    }

    public function testRemoveDuplicateImports()
    {
        $recipe = new BuildRecipe();
        $recipe->addImport('B');
        $recipe->addImport('A');
        $recipe->addImport('B');

        $this->assertSame(array('A', 'B'), $recipe->getImports());
    }

    public function testAddImports()
    {
        $recipe = new BuildRecipe();
        $recipe->addImports(array('A', 'B'));

        $this->assertSame(array('A', 'B'), $recipe->getImports());
    }

    public function testAddVarDeclaration()
    {
        $recipe = new BuildRecipe();
        $recipe->addVarDeclaration('$foo', '$foo = "Foo";');

        $this->assertSame(array(
            '$foo' => '$foo = "Foo";',
        ), $recipe->getVarDeclarations());
    }

    public function testAddVarDeclarations()
    {
        $recipe = new BuildRecipe();
        $recipe->addVarDeclarations(array(
            '$foo' => '$foo = "Foo";',
            '$bar' => '$bar = "Bar";',
        ));

        $this->assertSame(array(
            '$foo' => '$foo = "Foo";',
            '$bar' => '$bar = "Bar";',
        ), $recipe->getVarDeclarations());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddVarDeclarationFailsIfVarNameAlreadyUsed()
    {
        $recipe = new BuildRecipe();
        $recipe->addVarDeclaration('$foo', '$foo = "Foo";');
        $recipe->addVarDeclaration('$foo', '$foo = "Bar";');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testVariablesMustStartWithDollar()
    {
        $recipe = new BuildRecipe();
        $recipe->addVarDeclaration('foo', '$foo = "Foo";');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testVariablesMustOccurInCode()
    {
        $recipe = new BuildRecipe();
        $recipe->addVarDeclaration('$foo', '$bar = "Foo";');
    }
}
