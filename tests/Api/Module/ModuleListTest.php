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
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleListTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ModuleList
     */
    private $collection;

    protected function setUp()
    {
        $this->collection = new ModuleList();
    }

    public function testGetModule()
    {
        $moduleFile = new ModuleFile('vendor/module');
        $module = new Module($moduleFile, '/path');

        $this->collection->add($module);

        $this->assertSame($module, $this->collection->get('vendor/module'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Module\NoSuchModuleException
     */
    public function testGetModuleFailsIfNotFound()
    {
        $this->collection->get('vendor/module');
    }

    public function testGetRootModuleReturnsNull()
    {
        $this->assertNull($this->collection->getRootModule());
    }

    public function testGetRootModuleReturnsAddedRootModule()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path3');

        $this->collection->add($module1);
        $this->collection->add($rootModule);
        $this->collection->add($module2);

        $this->assertSame($rootModule, $this->collection->getRootModule());
    }

    public function testGetRootModuleName()
    {
        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path');

        $this->collection->add($rootModule);

        $this->assertSame('vendor/root', $this->collection->getRootModuleName());
    }

    public function testGetRootModuleNameReturnsNullIfNoRootModule()
    {
        $moduleFile = new ModuleFile('vendor/module');
        $module = new Module($moduleFile, '/path');

        $this->collection->add($module);

        $this->assertNull($this->collection->getRootModuleName());
    }

    public function testGetInstalledModules()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $this->collection->add($module1);
        $this->collection->add($module2);

        $this->assertSame(array(
            'vendor/module1' => $module1,
            'vendor/module2' => $module2,
        ), $this->collection->getInstalledModules());
    }

    public function testGetInstalledModulesDoesNotIncludeRoot()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path3');

        $this->collection->add($module1);
        $this->collection->add($rootModule);
        $this->collection->add($module2);

        $this->assertSame(array(
            'vendor/module1' => $module1,
            'vendor/module2' => $module2,
        ), $this->collection->getInstalledModules());
    }

    public function testGetInstalledModuleNames()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $this->collection->add($module1);
        $this->collection->add($module2);

        $this->assertSame(array('vendor/module1', 'vendor/module2'), $this->collection->getInstalledModuleNames());
    }

    public function testMerge()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $moduleFile3 = new ModuleFile('vendor/module3');
        $module3 = new Module($moduleFile3, '/path3');

        $this->collection->add($module1);
        $this->collection->merge(array($module2, $module3));

        $this->assertSame(array(
            'vendor/module1' => $module1,
            'vendor/module2' => $module2,
            'vendor/module3' => $module3,
        ), $this->collection->toArray());
    }

    public function testReplace()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $moduleFile3 = new ModuleFile('vendor/module3');
        $module3 = new Module($moduleFile3, '/path3');

        $this->collection->add($module1);
        $this->collection->replace(array($module2, $module3));

        $this->assertSame(array(
            'vendor/module2' => $module2,
            'vendor/module3' => $module3,
        ), $this->collection->toArray());
    }

    public function testRemove()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $this->collection->add($module1);
        $this->collection->add($module2);

        $this->collection->remove('vendor/module1');

        $this->assertFalse($this->collection->contains('vendor/module1'));
        $this->assertTrue($this->collection->contains('vendor/module2'));
    }

    public function testRemoveUnknown()
    {
        $this->collection->remove('foo');

        $this->assertFalse($this->collection->contains('foo'));
    }

    public function testRemoveRoot()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path3');

        $this->collection->add($module1);
        $this->collection->add($rootModule);
        $this->collection->add($module2);

        $this->collection->remove('vendor/root');

        $this->assertFalse($this->collection->contains('vendor/root'));
        $this->assertTrue($this->collection->contains('vendor/module1'));
        $this->assertTrue($this->collection->contains('vendor/module2'));

        $this->assertNull($this->collection->getRootModule());
    }

    public function testClear()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path3');

        $this->collection->add($module1);
        $this->collection->add($rootModule);
        $this->collection->add($module2);

        $this->collection->clear();

        $this->assertFalse($this->collection->contains('vendor/root'));
        $this->assertFalse($this->collection->contains('vendor/module1'));
        $this->assertFalse($this->collection->contains('vendor/module2'));
        $this->assertTrue($this->collection->isEmpty());
    }

    public function testIterate()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path3');

        $this->collection->add($module1);
        $this->collection->add($rootModule);
        $this->collection->add($module2);

        $this->assertSame(array(
            'vendor/module1' => $module1,
            'vendor/root' => $rootModule,
            'vendor/module2' => $module2,
        ), iterator_to_array($this->collection));
    }

    public function testToArray()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path3');

        $this->collection->add($module1);
        $this->collection->add($rootModule);
        $this->collection->add($module2);

        $this->assertSame(array(
            'vendor/module1' => $module1,
            'vendor/root' => $rootModule,
            'vendor/module2' => $module2,
        ), $this->collection->toArray());
    }

    public function testArrayAccess()
    {
        $moduleFile1 = new ModuleFile('vendor/module1');
        $module1 = new Module($moduleFile1, '/path1');

        $moduleFile2 = new ModuleFile('vendor/module2');
        $module2 = new Module($moduleFile2, '/path2');

        $rootModuleFile = new RootModuleFile('vendor/root');
        $rootModule = new RootModule($rootModuleFile, '/path3');

        $this->assertFalse(isset($this->collection['vendor/module1']));
        $this->assertFalse(isset($this->collection['vendor/module2']));
        $this->assertFalse(isset($this->collection['vendor/root']));

        $this->collection[] = $module1;
        $this->collection[] = $module2;
        $this->collection[] = $rootModule;

        $this->assertTrue(isset($this->collection['vendor/module1']));
        $this->assertTrue(isset($this->collection['vendor/module2']));
        $this->assertTrue(isset($this->collection['vendor/root']));

        $this->assertSame($rootModule, $this->collection['vendor/root']);
        $this->assertSame($rootModule, $this->collection->getRootModule());
        $this->assertSame($module1, $this->collection['vendor/module1']);
        $this->assertSame($module2, $this->collection['vendor/module2']);

        unset($this->collection['vendor/module1']);

        $this->assertFalse(isset($this->collection['vendor/module1']));
        $this->assertTrue(isset($this->collection['vendor/module2']));
        $this->assertTrue(isset($this->collection['vendor/root']));
    }
}
