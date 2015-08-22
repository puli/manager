<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Php;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Php\Argument;
use Puli\Manager\Api\Php\Clazz;
use Puli\Manager\Api\Php\Import;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Api\Php\ReturnValue;
use Puli\Manager\Php\ClassWriter;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Glob\Test\TestUtil;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ClassWriterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ClassWriter
     */
    private $writer;

    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var Clazz
     */
    private $class;

    protected function setUp()
    {
        $this->tempDir = TestUtil::makeTempDir('puli-manager', __CLASS__);
        $this->class = new Clazz('MyClass');
        $this->class->setDirectory($this->tempDir);
        $this->writer = new ClassWriter();
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWriteFailsIfDirectoryNotSet()
    {
        $class = new Clazz('MyClass');

        $this->writer->writeClass($class);
    }

    public function testWriteEmptyClass()
    {
        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testCreateMissingDirectories()
    {
        $this->class->setDirectory($this->tempDir.'/sub');

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/sub/MyClass.php');
    }

    public function testWriteCustomFileName()
    {
        $this->class->setFileName('MyFileName.php');

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyFileName.php');
    }

    public function testWriteNamespace()
    {
        $this->class->setClassName('Puli\MyClass');

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

namespace Puli;

class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testIgnoreGlobalImportsInGlobalNamespace()
    {
        $this->class->addImport(new Import('IteratorAggregate'));
        $this->class->addImport(new Import('Puli\SomeClass'));
        $this->class->addImport(new Import('Acme\OtherClass'));

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

use Acme\OtherClass;
use Puli\SomeClass;

class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testIgnoreImportsFromSameNamespace()
    {
        $this->class->setClassName('Puli\MyClass');
        $this->class->addImport(new Import('IteratorAggregate'));
        $this->class->addImport(new Import('Puli\SomeClass'));
        $this->class->addImport(new Import('Acme\OtherClass'));

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

namespace Puli;

use Acme\OtherClass;
use IteratorAggregate;

class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteImportWithAlias()
    {
        $this->class->addImport(new Import('Puli\SomeClass', 'Alias'));

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

use Puli\SomeClass as Alias;

class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteParentClass()
    {
        $this->class->setParentClass('MyParentClass');

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass extends MyParentClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteInterfaces()
    {
        $this->class->addImplementedInterface('SomeInterface');
        $this->class->addImplementedInterface('OtherInterface');

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass implements SomeInterface, OtherInterface
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteParentClassAndInterfaces()
    {
        $this->class->setParentClass('MyParentClass');
        $this->class->addImplementedInterface('SomeInterface');
        $this->class->addImplementedInterface('OtherInterface');

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass extends MyParentClass implements SomeInterface, OtherInterface
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteDocBlock()
    {
        $this->class->setDescription("My\n\n  Doc\n    Block");

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

/**
 * My
 *
 *   Doc
 *     Block
 */
class MyClass
{
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteEmptyMethod()
    {
        $this->class->addMethod(new Method('doSomething'));

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    public function doSomething()
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMultipleMethods()
    {
        $this->class->addMethod(new Method('doSomething'));
        $this->class->addMethod(new Method('doSomethingElse'));

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    public function doSomething()
    {
    }

    public function doSomethingElse()
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithSingleArgument()
    {
        $method = new Method('doSomething');
        $method->addArgument(new Argument('arg1'));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @param mixed \$arg1
     */
    public function doSomething(\$arg1)
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithMultipleArguments()
    {
        $method = new Method('doSomething');
        $method->addArgument(new Argument('arg1'));
        $method->addArgument(new Argument('arg2'));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @param mixed \$arg1
     * @param mixed \$arg2
     */
    public function doSomething(\$arg1, \$arg2)
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteArgumentWithTypeHint()
    {
        $arg = new Argument('arg');
        $arg->setTypeHint('MyType');

        $method = new Method('doSomething');
        $method->addArgument($arg);

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @param mixed \$arg
     */
    public function doSomething(MyType \$arg)
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteArgumentWithType()
    {
        $arg = new Argument('arg');
        $arg->setType('MyType');

        $method = new Method('doSomething');
        $method->addArgument($arg);

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @param MyType \$arg
     */
    public function doSomething(\$arg)
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteArgumentWithDescription()
    {
        $arg = new Argument('arg');
        $arg->setDescription('The description');

        $method = new Method('doSomething');
        $method->addArgument($arg);

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @param mixed \$arg The description
     */
    public function doSomething(\$arg)
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteArgumentWithDefaultValue()
    {
        $arg = new Argument('arg');
        $arg->setDefaultValue("'The default'");

        $method = new Method('doSomething');
        $method->addArgument($arg);

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @param mixed \$arg
     */
    public function doSomething(\$arg = 'The default')
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithReturnValue()
    {
        $method = new Method('doSomething');
        $method->setReturnValue(new ReturnValue("'The return value'"));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @return mixed
     */
    public function doSomething()
    {
        return 'The return value';
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteReturnValueWithType()
    {
        $method = new Method('doSomething');
        $method->setReturnValue(new ReturnValue("'The return value'", 'string'));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @return string
     */
    public function doSomething()
    {
        return 'The return value';
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteReturnValueWithDescription()
    {
        $method = new Method('doSomething');
        $method->setReturnValue(new ReturnValue("'The return value'", 'string', 'The description'));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @return string The description
     */
    public function doSomething()
    {
        return 'The return value';
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithArgumentsAndReturnValue()
    {
        $method = new Method('doSomething');
        $method->addArgument(new Argument('arg'));
        $method->setReturnValue(new ReturnValue("'The return value'"));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @param mixed \$arg
     *
     * @return mixed
     */
    public function doSomething(\$arg)
    {
        return 'The return value';
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithBody()
    {
        $method = new Method('doSomething');
        $method->setBody('$foo = \'bar\';');

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    public function doSomething()
    {
        \$foo = 'bar';
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteBodyWithEmptyLines()
    {
        $method = new Method('doSomething');
        $method->setBody(
<<<EOF
\$foo = 'bar';

\$bar = 'baz';
EOF
        );

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    public function doSomething()
    {
        \$foo = 'bar';

        \$bar = 'baz';
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithBodyAndReturnValue()
    {
        $method = new Method('doSomething');
        $method->setBody('$foo = \'bar\';');
        $method->setReturnValue(new ReturnValue('$foo'));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * @return mixed
     */
    public function doSomething()
    {
        \$foo = 'bar';

        return \$foo;
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithDocBlock()
    {
        $method = new Method('doSomething');
        $method->setDescription("The\n\n  Doc\n    Block");

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * The
     *
     *   Doc
     *     Block
     */
    public function doSomething()
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithDocBlockAndArgument()
    {
        $method = new Method('doSomething');
        $method->setDescription("The\n  Doc\n    Block");
        $method->addArgument(new Argument('arg'));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * The
     *   Doc
     *     Block
     *
     * @param mixed \$arg
     */
    public function doSomething(\$arg)
    {
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    public function testWriteMethodWithDocBlockAndReturnValue()
    {
        $method = new Method('doSomething');
        $method->setDescription("The\n  Doc\n    Block");
        $method->setReturnValue(new ReturnValue('42'));

        $this->class->addMethod($method);

        $this->writer->writeClass($this->class);

        $expected = <<<EOF
<?php

class MyClass
{
    /**
     * The
     *   Doc
     *     Block
     *
     * @return mixed
     */
    public function doSomething()
    {
        return 42;
    }
}

EOF;

        $this->assertFileSame($expected, $this->tempDir.'/MyClass.php');
    }

    private function assertFileSame($expected, $file)
    {
        $this->assertFileExists($file);
        $this->assertSame($expected, file_get_contents($file));
    }
}
