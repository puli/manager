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
use Puli\Manager\Api\Php\Clazz;
use Puli\Manager\Api\Php\Import;
use Puli\Manager\Api\Php\Method;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ClazzTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Clazz
     */
    private $class;

    protected function setUp()
    {
        $this->class = new Clazz('Puli\MyClass');
    }

    public function testGetClassName()
    {
        $this->assertSame('Puli\MyClass', $this->class->getClassName());
    }

    public function testSetClassNameWithNamespace()
    {
        $this->class->setClassName('Puli\MyClass');

        $this->assertSame('Puli\MyClass', $this->class->getClassName());
        $this->assertSame('MyClass', $this->class->getShortClassName());
        $this->assertSame('Puli', $this->class->getNamespaceName());
    }

    public function testSetClassNameWithoutNamespace()
    {
        $this->class->setClassName('MyClass');

        $this->assertSame('MyClass', $this->class->getClassName());
        $this->assertSame('MyClass', $this->class->getShortClassName());
        $this->assertSame('', $this->class->getNamespaceName());
    }

    public function testSetClassNameWithRootNamespace()
    {
        $this->class->setClassName('\MyClass');

        $this->assertSame('MyClass', $this->class->getClassName());
        $this->assertSame('MyClass', $this->class->getShortClassName());
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
        $this->assertSame('MyClass.php', $this->class->getFileName());
    }

    public function testResetFileName()
    {
        $this->class->setFileName('MyFile.php');
        $this->class->resetFileName();

        $this->assertSame('MyClass.php', $this->class->getFileName());
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
        $this->class->addImport($iterator = new Import('IteratorAggregate'));
        $this->class->addImport($countable = new Import('Countable'));

        // Result is sorted
        $this->assertSame(array(
            'Countable' => $countable,
            'IteratorAggregate' => $iterator,
        ), $this->class->getImports());
    }

    public function testAddImportIgnoresDuplicateImports()
    {
        $this->class->addImport($iterator = new Import('IteratorAggregate'));
        $this->class->addImport(new Import('IteratorAggregate'));

        $this->assertSame(array(
            'IteratorAggregate' => $iterator,
        ), $this->class->getImports());
    }

    public function testAddImportSucceedsIfDuplicatedSymbolHasAlias()
    {
        $this->class->addImport($countable1 = new Import('Countable'));
        $this->class->addImport($countable2 = new Import('Acme\Countable', 'Alias'));

        $this->assertSame(array(
            'Acme\Countable' => $countable2,
            'Countable' => $countable1,
        ), $this->class->getImports());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddImportFailsIfShortClassClashesWithExistingClass()
    {
        $this->class->addImport(new Import('Countable'));
        $this->class->addImport(new Import('Acme\Countable'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddImportFailsIfShortClassClashesWithExistingAlias()
    {
        $this->class->addImport(new Import('MyClass', 'Countable'));
        $this->class->addImport(new Import('Acme\Countable'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddImportFailsIfAliasClashesWithExistingClass()
    {
        $this->class->addImport(new Import('Countable'));
        $this->class->addImport(new Import('Acme\MyClass', 'Countable'));
    }

    public function testAddImports()
    {
        $this->class->addImport($iterator = new Import('IteratorAggregate'));
        $this->class->addImports(array(
            $countable = new Import('Countable'),
            $arrayAccess = new Import('ArrayAccess'),
        ));

        $this->assertSame(array(
            'ArrayAccess' => $arrayAccess,
            'Countable' => $countable,
            'IteratorAggregate' => $iterator,
        ), $this->class->getImports());
    }

    public function testSetImports()
    {
        $this->class->addImport($iterator = new Import('IteratorAggregate'));
        $this->class->setImports(array(
            $countable = new Import('Countable'),
            $arrayAccess = new Import('ArrayAccess'),
        ));

        $this->assertSame(array(
            'ArrayAccess' => $arrayAccess,
            'Countable' => $countable,
        ), $this->class->getImports());
    }

    public function testRemoveImport()
    {
        $this->class->addImport(new Import('IteratorAggregate'));
        $this->class->addImport($countable = new Import('Countable'));
        $this->class->removeImport('IteratorAggregate');

        $this->assertSame(array(
            'Countable' => $countable,
        ), $this->class->getImports());
    }

    public function testClearImports()
    {
        $this->class->addImport(new Import('IteratorAggregate'));
        $this->class->addImport(new Import('Countable'));
        $this->class->clearImports();

        $this->assertSame(array(), $this->class->getImports());
    }

    public function testHasImports()
    {
        $this->assertFalse($this->class->hasImports());

        $this->class->addImport(new Import('IteratorAggregate'));

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
