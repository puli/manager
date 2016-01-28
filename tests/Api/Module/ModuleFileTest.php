<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Module;

use PHPUnit_Framework_TestCase;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Tests\Discovery\Fixtures\Foo;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleFileTest extends PHPUnit_Framework_TestCase
{
    public function provideValidPaths()
    {
        return array(
            array(null),
            array('/foo'),
        );
    }

    /**
     * @dataProvider provideValidPaths
     */
    public function testGetPath($path)
    {
        $moduleFile = new ModuleFile(null, $path);

        $this->assertSame($path, $moduleFile->getPath());
    }

    public function provideInvalidPaths()
    {
        return array(
            array(12345),
            array(''),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustBeValid($invalidPath)
    {
        new ModuleFile(null, $invalidPath);
    }

    public function provideValidModuleNames()
    {
        return array(
            array(null),
            array('/foo'),
        );
    }

    /**
     * @dataProvider provideValidModuleNames
     */
    public function testGetModuleName($name)
    {
        $moduleFile = new ModuleFile($name);

        $this->assertSame($name, $moduleFile->getModuleName());
    }

    /**
     * @dataProvider provideValidModuleNames
     */
    public function testGetModuleNameSetter($name)
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setModuleName($name);

        $this->assertSame($name, $moduleFile->getModuleName());
    }

    public function provideInvalidModuleNames()
    {
        return array(
            array(12345),
            array(''),
        );
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testModuleNameMustBeValid($invalidName)
    {
        new ModuleFile($invalidName);
    }

    /**
     * @dataProvider provideInvalidPaths
     * @expectedException \InvalidArgumentException
     */
    public function testModuleNameMustBeValidSetter($invalidName)
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setModuleName($invalidName);
    }

    public function testAddPathMapping()
    {
        $mapping1 = new PathMapping('/path1', 'res1');
        $mapping2 = new PathMapping('/path2', array('res2', 'res3'));

        $moduleFile = new ModuleFile();
        $moduleFile->addPathMapping($mapping1);
        $moduleFile->addPathMapping($mapping2);

        $this->assertSame(array(
            '/path1' => $mapping1,
            '/path2' => $mapping2,
        ), $moduleFile->getPathMappings());
    }

    public function testGetPathMappingsReturnsSortedResult()
    {
        $mapping1 = new PathMapping('/path1', 'res1');
        $mapping2 = new PathMapping('/path2', 'res2');
        $mapping3 = new PathMapping('/path3', 'res3');

        $moduleFile = new ModuleFile();
        $moduleFile->addPathMapping($mapping3);
        $moduleFile->addPathMapping($mapping1);
        $moduleFile->addPathMapping($mapping2);

        $this->assertSame(array(
            '/path1' => $mapping1,
            '/path2' => $mapping2,
            '/path3' => $mapping3,
        ), $moduleFile->getPathMappings());
    }

    public function testGetPathMapping()
    {
        $mapping1 = new PathMapping('/path1', 'res1');
        $mapping2 = new PathMapping('/path2', array('res2', 'res3'));

        $moduleFile = new ModuleFile();
        $moduleFile->addPathMapping($mapping1);
        $moduleFile->addPathMapping($mapping2);

        $this->assertSame($mapping1, $moduleFile->getPathMapping('/path1'));
        $this->assertSame($mapping2, $moduleFile->getPathMapping('/path2'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Repository\NoSuchPathMappingException
     * @expectedExceptionMessage foobar
     */
    public function testGetPathMappingFailsIfPathNotFound()
    {
        $moduleFile = new ModuleFile();

        $moduleFile->getPathMapping('/foobar');
    }

    public function testHasPathMapping()
    {
        $moduleFile = new ModuleFile();

        $this->assertFalse($moduleFile->hasPathMapping('/path'));

        $moduleFile->addPathMapping(new PathMapping('/path', 'res'));

        $this->assertTrue($moduleFile->hasPathMapping('/path'));
    }

    public function testRemovePathMapping()
    {
        $moduleFile = new ModuleFile();

        $moduleFile->addPathMapping(new PathMapping('/path', 'res'));
        $moduleFile->removePathMapping('/path');

        $this->assertFalse($moduleFile->hasPathMapping('/path'));
    }

    public function testRemovePathMappingIgnoresUnknownPaths()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->removePathMapping('/foobar');

        $this->assertFalse($moduleFile->hasPathMapping('/foobar'));
    }

    public function testSetOverriddenModules()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setOverriddenModules(array('module1', 'module2'));

        $this->assertSame(array('module1', 'module2'), $moduleFile->getOverriddenModules());
    }

    public function testAddOverriddenModule()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setOverriddenModules(array('module1'));
        $moduleFile->addOverriddenModule('module2');

        $this->assertSame(array('module1', 'module2'), $moduleFile->getOverriddenModules());
    }

    public function testAddOverriddenModuleIgnoresDuplicates()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setOverriddenModules(array('module1'));
        $moduleFile->addOverriddenModule('module1');

        $this->assertSame(array('module1'), $moduleFile->getOverriddenModules());
    }

    public function testAddBindingDescriptor()
    {
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);

        $moduleFile = new ModuleFile();
        $moduleFile->addBindingDescriptor($descriptor);

        $this->assertSame($descriptor, $moduleFile->getBindingDescriptor($binding->getUuid()));
        $this->assertSame(array($descriptor), $moduleFile->getBindingDescriptors());
    }

    public function testRemoveBindingDescriptor()
    {
        $binding1 = new ClassBinding(__CLASS__, Foo::clazz);
        $binding2 = new ResourceBinding('/path', Foo::clazz);
        $descriptor1 = new BindingDescriptor($binding1);
        $descriptor2 = new BindingDescriptor($binding2);

        $moduleFile = new ModuleFile();
        $moduleFile->addBindingDescriptor($descriptor1);
        $moduleFile->addBindingDescriptor($descriptor2);
        $moduleFile->removeBindingDescriptor($binding1->getUuid());

        $this->assertSame(array($descriptor2), $moduleFile->getBindingDescriptors());
    }

    public function testHasBindingDescriptor()
    {
        $binding = new ClassBinding(__CLASS__, Foo::clazz);
        $descriptor = new BindingDescriptor($binding);

        $moduleFile = new ModuleFile();

        $this->assertFalse($moduleFile->hasBindingDescriptor($binding->getUuid()));
        $moduleFile->addBindingDescriptor($descriptor);
        $this->assertTrue($moduleFile->hasBindingDescriptor($binding->getUuid()));
    }

    /**
     * @expectedException \Puli\Manager\Api\Discovery\NoSuchBindingException
     * @expectedExceptionMessage 8546da2c-dfec-48be-8cd3-93798c41b72f
     */
    public function testGetBindingDescriptorFailsIfUnknownUuid()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->getBindingDescriptor(Uuid::fromString('8546da2c-dfec-48be-8cd3-93798c41b72f'));
    }

    public function testSetExtraKey()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key1', 'value1');
        $moduleFile->setExtraKey('key2', 'value2');

        $this->assertSame(array('key1' => 'value1', 'key2' => 'value2'), $moduleFile->getExtraKeys());
    }

    public function testSetExtraKeys()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key1', 'value1');
        $moduleFile->setExtraKeys(array(
            'key2' => 'value2',
            'key3' => 'value3',
        ));

        $this->assertSame(array('key2' => 'value2', 'key3' => 'value3'), $moduleFile->getExtraKeys());
    }

    public function testAddExtraKeys()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key1', 'value1');
        $moduleFile->addExtraKeys(array(
            'key2' => 'value2',
            'key3' => 'value3',
        ));

        $this->assertSame(array('key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'), $moduleFile->getExtraKeys());
    }

    public function testRemoveExtraKey()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key1', 'value1');
        $moduleFile->setExtraKey('key2', 'value2');
        $moduleFile->removeExtraKey('key1');

        $this->assertSame(array('key2' => 'value2'), $moduleFile->getExtraKeys());
    }

    public function testClearExtraKeys()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key1', 'value1');
        $moduleFile->setExtraKey('key2', 'value2');
        $moduleFile->clearExtraKeys();

        $this->assertSame(array(), $moduleFile->getExtraKeys());
    }

    public function testGetExtraKey()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key1', 'value1');
        $moduleFile->setExtraKey('key2', 'value2');

        $this->assertSame('value1', $moduleFile->getExtraKey('key1'));
        $this->assertSame('value2', $moduleFile->getExtraKey('key2'));
    }

    public function testGetExtraKeyReturnsDefaultIfNotFound()
    {
        $moduleFile = new ModuleFile();

        $this->assertNull($moduleFile->getExtraKey('foobar'));
        $this->assertSame('default', $moduleFile->getExtraKey('foobar', 'default'));
    }

    public function testGetExtraKeyDoesNotReturnDefaultIfNull()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key', null);

        $this->assertNull($moduleFile->getExtraKey('key', 'default'));
    }

    public function testHasExtraKey()
    {
        $moduleFile = new ModuleFile();
        $moduleFile->setExtraKey('key', 'value');

        $this->assertTrue($moduleFile->hasExtraKey('key'));
        $this->assertFalse($moduleFile->hasExtraKey('foobar'));
    }

    public function testHasExtraKeys()
    {
        $moduleFile = new ModuleFile();
        $this->assertFalse($moduleFile->hasExtraKeys());
        $moduleFile->setExtraKey('key', null);
        $this->assertTrue($moduleFile->hasExtraKey('key'));
        $moduleFile->clearExtraKeys();
        $this->assertFalse($moduleFile->hasExtraKeys());
    }
}
