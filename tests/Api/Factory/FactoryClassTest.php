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
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FactoryClassTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var FactoryClass
     */
    private $class;

    protected function setUp()
    {
        $this->class = new FactoryClass('Puli\GeneratedFactory');
    }

    public function testGetClassName()
    {
        $this->assertSame('Puli\GeneratedFactory', $this->class->getClassName());
    }

    public function testSetClassNameWithNamespace()
    {
        $this->class->setClassName('Puli\GeneratedFactory');

        $this->assertSame('Puli\GeneratedFactory', $this->class->getClassName());
        $this->assertSame('GeneratedFactory', $this->class->getShortClassName());
        $this->assertSame('Puli', $this->class->getNamespaceName());
    }

    public function testSetClassNameWithoutNamespace()
    {
        $this->class->setClassName('GeneratedFactory');

        $this->assertSame('GeneratedFactory', $this->class->getClassName());
        $this->assertSame('GeneratedFactory', $this->class->getShortClassName());
        $this->assertSame('', $this->class->getNamespaceName());
    }

    public function testSetClassNameWithRootNamespace()
    {
        $this->class->setClassName('\GeneratedFactory');

        $this->assertSame('GeneratedFactory', $this->class->getClassName());
        $this->assertSame('GeneratedFactory', $this->class->getShortClassName());
        $this->assertSame('', $this->class->getNamespaceName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetClassNameFailsIfNull()
    {
        $this->class->setClassName(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetClassNameFailsIfEmpty()
    {
        $this->class->setClassName('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetClassNameFailsIfNoString()
    {
        $this->class->setClassName(1234);
    }

    public function testSetDirectory()
    {
        $this->class->setDirectory(__DIR__.'/..');

        $this->assertSame(Path::canonicalize(__DIR__.'/..'), $this->class->getDirectory());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDirectoryFailsIfNull()
    {
        $this->class->setDirectory(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDirectoryFailsIfEmpty()
    {
        $this->class->setDirectory('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDirectoryFailsIfNoString()
    {
        $this->class->setDirectory(1234);
    }

    public function testSetFileName()
    {
        $this->class->setFileName('MyFile.php');

        $this->assertSame('MyFile.php', $this->class->getFileName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetFileNameFailsIfNull()
    {
        $this->class->setFileName(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetFileNameFailsIfEmpty()
    {
        $this->class->setFileName('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetFileNameFailsIfNoString()
    {
        $this->class->setFileName(1234);
    }

    public function testGetDefaultFileName()
    {
        $this->assertSame('GeneratedFactory.php', $this->class->getFileName());
    }

    public function testResetFileName()
    {
        $this->class->setFileName('MyFile.php');
        $this->class->resetFileName();

        $this->assertSame('GeneratedFactory.php', $this->class->getFileName());
    }

    public function testGetFilePath()
    {
        $this->class->setDirectory(__DIR__);
        $this->class->setFileName('MyFile.php');

        $this->assertSame(__DIR__.'/MyFile.php', $this->class->getFilePath());
    }

    public function testSetFilePath()
    {
        $this->class->setFilePath(__DIR__.'/../MyFile.php');

        $this->assertSame(Path::canonicalize(__DIR__.'/..'), $this->class->getDirectory());
        $this->assertSame('MyFile.php', $this->class->getFileName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetFilePathFailsIfNull()
    {
        $this->class->setFilePath(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetFilePathFailsIfEmpty()
    {
        $this->class->setFilePath('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetFilePathFailsIfNoString()
    {
        $this->class->setFilePath(1234);
    }

    public function testSetParentClass()
    {
        $this->class->setParentClass('stdClass');

        $this->assertSame('stdClass', $this->class->getParentClass());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetParentClassFailsIfNull()
    {
        $this->class->setParentClass(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetParentClassFailsIfEmpty()
    {
        $this->class->setParentClass('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetParentClassFailsIfNoString()
    {
        $this->class->setParentClass(1234);
    }

    public function testRemoveParentClass()
    {
        $this->class->setParentClass('stdClass');
        $this->class->removeParentClass();

        $this->assertNull($this->class->getParentClass());
    }

    public function testHasParentClass()
    {
        $this->assertFalse($this->class->hasParentClass());

        $this->class->setParentClass('stdClass');

        $this->assertTrue($this->class->hasParentClass());

        $this->class->removeParentClass();

        $this->assertFalse($this->class->hasParentClass());
    }

    public function testAddImplementedInterface()
    {
        $this->class->addImplementedInterface('IteratorAggregate');
        $this->class->addImplementedInterface('Countable');

        $this->assertSame(array(
            'IteratorAggregate',
            'Countable',
        ), $this->class->getImplementedInterfaces());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddImplementedInterfaceFailsIfNull()
    {
        $this->class->addImplementedInterface(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddImplementedInterfaceFailsIfEmpty()
    {
        $this->class->addImplementedInterface('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddImplementedInterfaceFailsIfNoString()
    {
        $this->class->addImplementedInterface(1234);
    }

    public function testAddImplementedInterfaces()
    {
        $this->class->addImplementedInterface('IteratorAggregate');
        $this->class->addImplementedInterfaces(array('Countable', 'ArrayAccess'));

        $this->assertSame(array(
            'IteratorAggregate',
            'Countable',
            'ArrayAccess',
        ), $this->class->getImplementedInterfaces());
    }

    public function testSetImplementedInterfaces()
    {
        $this->class->addImplementedInterface('IteratorAggregate');
        $this->class->setImplementedInterfaces(array('Countable', 'ArrayAccess'));

        $this->assertSame(array(
            'Countable',
            'ArrayAccess',
        ), $this->class->getImplementedInterfaces());
    }

    public function testRemoveImplementedInterface()
    {
        $this->class->addImplementedInterface('IteratorAggregate');
        $this->class->addImplementedInterface('Countable');
        $this->class->removeImplementedInterface('IteratorAggregate');

        $this->assertSame(array('Countable'), $this->class->getImplementedInterfaces());
    }

    public function testRemoveImplementedInterfaceIgnoresUnknownInterface()
    {
        $this->class->addImplementedInterface('IteratorAggregate');
        $this->class->addImplementedInterface('Countable');

        $this->class->removeImplementedInterface('Foobar');

        $this->assertSame(array(
            'IteratorAggregate',
            'Countable',
        ), $this->class->getImplementedInterfaces());
    }

    public function testClearImplementedInterfaces()
    {
        $this->class->addImplementedInterface('IteratorAggregate');
        $this->class->addImplementedInterface('Countable');

        $this->class->clearImplementedInterfaces();

        $this->assertSame(array(), $this->class->getImplementedInterfaces());
    }

    public function testHasImplementedInterfaces()
    {
        $this->assertFalse($this->class->hasImplementedInterfaces());

        $this->class->addImplementedInterface('IteratorAggregate');

        $this->assertTrue($this->class->hasImplementedInterfaces());

        $this->class->removeImplementedInterface('IteratorAggregate');

        $this->assertFalse($this->class->hasImplementedInterfaces());
    }

    public function testAddImport()
    {
        $this->class->addImport('IteratorAggregate');
        $this->class->addImport('Countable');

        $this->assertSame(array(
            'IteratorAggregate',
            'Countable',
        ), $this->class->getImports());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddImportFailsIfNull()
    {
        $this->class->addImport(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddImportFailsIfEmpty()
    {
        $this->class->addImport('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddImportFailsIfNoString()
    {
        $this->class->addImport(1234);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddImportFailsIfIntroducingDuplicateSymbol()
    {
        $this->class->addImport('Countable');
        $this->class->addImport('Acme\Countable');
    }

    public function testAddImports()
    {
        $this->class->addImport('IteratorAggregate');
        $this->class->addImports(array(
            'Countable',
            'ArrayAccess',
        ));

        $this->assertSame(array(
            'IteratorAggregate',
            'Countable',
            'ArrayAccess',
        ), $this->class->getImports());
    }

    public function testSetImports()
    {
        $this->class->addImport('IteratorAggregate');
        $this->class->setImports(array(
            'Countable',
            'ArrayAccess',
        ));

        $this->assertSame(array(
            'Countable',
            'ArrayAccess',
        ), $this->class->getImports());
    }

    public function testRemoveImport()
    {
        $this->class->addImport('IteratorAggregate');
        $this->class->addImport('Countable');
        $this->class->removeImport('IteratorAggregate');

        $this->assertSame(array(
            'Countable',
        ), $this->class->getImports());
    }

    public function testClearImports()
    {
        $this->class->addImport('IteratorAggregate');
        $this->class->addImport('Countable');
        $this->class->clearImports();

        $this->assertSame(array(), $this->class->getImports());
    }

    public function testHasImports()
    {
        $this->assertFalse($this->class->hasImports());

        $this->class->addImport('IteratorAggregate');

        $this->assertTrue($this->class->hasImports());

        $this->class->removeImport('IteratorAggregate');

        $this->assertFalse($this->class->hasImports());
    }

    public function testAddMethod()
    {
        $method1 = new Method('doSomething');
        $method2 = new Method('doSomethingElse');

        $this->class->addMethod($method1);
        $this->class->addMethod($method2);

        $this->assertSame(array(
            'doSomething' => $method1,
            'doSomethingElse' => $method2,
        ), $this->class->getMethods());

        $this->assertSame($this->class, $method1->getClass());
        $this->assertSame($this->class, $method2->getClass());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddMethodFailsIfDuplicate()
    {
        $this->class->addMethod(new Method('doSomething'));
        $this->class->addMethod(new Method('doSomething'));
    }

    public function testAddMethods()
    {
        $method1 = new Method('doSomething');
        $method2 = new Method('doSomethingElse');
        $method3 = new Method('doAnotherThing');

        $this->class->addMethod($method1);
        $this->class->addMethods(array($method2, $method3));

        $this->assertSame(array(
            'doSomething' => $method1,
            'doSomethingElse' => $method2,
            'doAnotherThing' => $method3,
        ), $this->class->getMethods());
    }

    public function testSetMethods()
    {
        $method1 = new Method('doSomething');
        $method2 = new Method('doSomethingElse');
        $method3 = new Method('doAnotherThing');

        $this->class->addMethod($method1);
        $this->class->setMethods(array($method2, $method3));

        $this->assertSame(array(
            'doSomethingElse' => $method2,
            'doAnotherThing' => $method3,
        ), $this->class->getMethods());
    }

    public function testRemoveMethod()
    {
        $method1 = new Method('doSomething');
        $method2 = new Method('doSomethingElse');

        $this->class->addMethod($method1);
        $this->class->addMethod($method2);
        $this->class->removeMethod('doSomething');

        $this->assertSame(array(
            'doSomethingElse' => $method2,
        ), $this->class->getMethods());
    }

    public function testRemoveMethodIgnoresUnknownMethod()
    {
        $method1 = new Method('doSomething');
        $method2 = new Method('doSomethingElse');

        $this->class->addMethod($method1);
        $this->class->addMethod($method2);
        $this->class->removeMethod('foobar');

        $this->assertSame(array(
            'doSomething' => $method1,
            'doSomethingElse' => $method2,
        ), $this->class->getMethods());
    }

    public function testClearMethods()
    {
        $method1 = new Method('doSomething');
        $method2 = new Method('doSomethingElse');

        $this->class->addMethod($method1);
        $this->class->addMethod($method2);
        $this->class->clearMethods();

        $this->assertSame(array(), $this->class->getMethods());
    }

    public function testGetMethod()
    {
        $this->class->addMethod($method = new Method('doSomething'));

        $this->assertSame($method, $this->class->getMethod('doSomething'));
    }

    /**
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage foobar
     */
    public function testGetMethodFailsIfNotFound()
    {
        $this->class->getMethod('foobar');
    }

    public function testHasMethod()
    {
        $this->assertFalse($this->class->hasMethod('doSomething'));

        $this->class->addMethod(new Method('doSomething'));

        $this->assertTrue($this->class->hasMethod('doSomething'));

        $this->class->removeMethod('doSomething');

        $this->assertFalse($this->class->hasMethod('doSomething'));
    }

    public function testHasMethods()
    {
        $this->assertFalse($this->class->hasMethods());

        $this->class->addMethod(new Method('doSomething'));

        $this->assertTrue($this->class->hasMethods());

        $this->class->removeMethod('doSomething');

        $this->assertFalse($this->class->hasMethods());
    }

    public function testSetDescription()
    {
        $this->class->setDescription('The description');

        $this->assertSame('The description', $this->class->getDescription());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNull()
    {
        $this->class->setDescription(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfEmpty()
    {
        $this->class->setDescription('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDescriptionFailsIfNoString()
    {
        $this->class->setDescription(1234);
    }
}
