<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Conflict;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Conflict\DependencyGraph;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DependencyGraphTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var DependencyGraph
     */
    private $graph;

    protected function setUp()
    {
        $this->graph = new DependencyGraph();
    }

    private function initializeGraph()
    {
        // (m1) → (m2)   →   (m3)
        //       ↗    ↘     ↗
        //   (m5)       (m4)
        //
        //        (m6)

        $this->graph->addModuleName('m1');
        $this->graph->addModuleName('m2');
        $this->graph->addModuleName('m3');
        $this->graph->addModuleName('m4');
        $this->graph->addModuleName('m5');
        $this->graph->addModuleName('m6');

        $this->graph->addDependency('m2', 'm1');
        $this->graph->addDependency('m3', 'm2');
        $this->graph->addDependency('m4', 'm2');
        $this->graph->addDependency('m2', 'm5');
        $this->graph->addDependency('m3', 'm4');
    }

    public function providePaths()
    {
        return array(
            // adjacent
            array('m1', 'm2', array('m1', 'm2')),

            // adjacent, wrong order
            array('m2', 'm1', null),

            // multi-node
            array('m1', 'm3', array('m1', 'm2', 'm3')),

            // multi-node, wrong order
            array('m3', 'm1', null),

            // multi-node, no path
            array('m3', 'm4', null),

            // node without edges
            array('m1', 'm5', null),
            array('m5', 'm1', null),

            // undefined node
            array('m1', 'foo', null),
            array('foo', 'm1', null),
        );
    }

    /**
     * @dataProvider providePaths
     */
    public function testHasPath($from, $to, $path)
    {
        $this->initializeGraph();

        $this->assertSame($path !== null, $this->graph->hasPath($to, $from));
    }

    /**
     * @dataProvider providePaths
     */
    public function testGetPath($from, $to, $path)
    {
        $this->initializeGraph();

        $this->assertSame($path, $this->graph->getPath($to, $from));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddModuleNameFailsIfAlreadyDefined()
    {
        $this->graph->addModuleName('m1');
        $this->graph->addModuleName('m1');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddDependencyFailsIfLeftModuleDoesNotExist()
    {
        $this->graph->addModuleName('m2');
        $this->graph->addDependency('m2', 'm1');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddDependencyFailsIfRightModuleDoesNotExist()
    {
        $this->graph->addModuleName('m1');
        $this->graph->addDependency('m2', 'm1');
    }

    /**
     * @expectedException \Puli\Manager\Conflict\CyclicDependencyException
     */
    public function testAddDependencyFailsIfCycle()
    {
        $this->graph->addModuleName('m1');
        $this->graph->addModuleName('m2');
        $this->graph->addDependency('m2', 'm1');
        $this->graph->addDependency('m1', 'm2');
    }

    public function testGetSortedModuleNames()
    {
        $this->initializeGraph();

        $this->assertSame(array('m1', 'm5', 'm2', 'm4', 'm3', 'm6'), $this->graph->getSortedModuleNames());
    }

    public function testGetSortedModuleNamesOfSubset()
    {
        $this->initializeGraph();

        $this->assertSame(array('m1', 'm4', 'm3', 'm5', 'm6'), $this->graph->getSortedModuleNames(array('m1', 'm3', 'm4', 'm5', 'm6')));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetSortedModuleNamesExpectsValidModules()
    {
        $this->graph->getSortedModuleNames(array('foo'));
    }

    public function testHasModuleName()
    {
        $this->assertFalse($this->graph->hasModuleName('m1'));

        $this->graph->addModuleName('m1');

        $this->assertTrue($this->graph->hasModuleName('m1'));
    }

    public function testHasDependency()
    {
        $this->assertFalse($this->graph->hasDependency('m2', 'm1'));
        $this->assertFalse($this->graph->hasDependency('m1', 'm2'));

        $this->graph->addModuleName('m1');
        $this->graph->addModuleName('m2');
        $this->graph->addModuleName('m3');

        $this->assertFalse($this->graph->hasDependency('m2', 'm1'));
        $this->assertFalse($this->graph->hasDependency('m1', 'm2'));

        $this->graph->addDependency('m2', 'm1');

        $this->assertTrue($this->graph->hasDependency('m2', 'm1'));
        $this->assertFalse($this->graph->hasDependency('m1', 'm2'));

        $this->graph->addDependency('m3', 'm2');

        $this->assertTrue($this->graph->hasDependency('m3', 'm2'));
        $this->assertTrue($this->graph->hasDependency('m3', 'm1'));
        $this->assertTrue($this->graph->hasDependency('m3', 'm2', false));
        $this->assertFalse($this->graph->hasDependency('m3', 'm1', false));
        $this->assertFalse($this->graph->hasDependency('m1', 'm3'));
    }

    public function testAddModuleNames()
    {
        $this->assertFalse($this->graph->hasModuleName('m1'));
        $this->assertFalse($this->graph->hasModuleName('m2'));

        $this->graph->addModuleNames(array('m1', 'm2'));

        $this->assertTrue($this->graph->hasModuleName('m1'));
        $this->assertTrue($this->graph->hasModuleName('m2'));
    }

    public function testCreateWithModuleNames()
    {
        $this->graph = new DependencyGraph(array('m1', 'm2'));

        $this->assertTrue($this->graph->hasModuleName('m1'));
        $this->assertTrue($this->graph->hasModuleName('m2'));
    }

    public function testForModules()
    {
        $modules = new ModuleList();
        $modules->add(new RootModule(new RootModuleFile('vendor/root'), __DIR__));
        $modules->add(new Module(new ModuleFile('vendor/module1'), __DIR__));
        $modules->add(new Module(new ModuleFile('vendor/module2'), __DIR__));
        $modules->add(new Module(new ModuleFile('vendor/module3'), __DIR__));

        $modules->get('vendor/module2')->getModuleFile()->addDependency('vendor/module1');
        $modules->get('vendor/module3')->getModuleFile()->addDependency('vendor/module1');
        $modules->get('vendor/module3')->getModuleFile()->addDependency('vendor/module2');

        $this->graph = DependencyGraph::forModules($modules);

        $this->assertTrue($this->graph->hasModuleName('vendor/root'));
        $this->assertTrue($this->graph->hasModuleName('vendor/module1'));
        $this->assertTrue($this->graph->hasModuleName('vendor/module2'));
        $this->assertTrue($this->graph->hasModuleName('vendor/module3'));
        $this->assertTrue($this->graph->hasDependency('vendor/module2', 'vendor/module1'));
        $this->assertTrue($this->graph->hasDependency('vendor/module3', 'vendor/module1'));
        $this->assertTrue($this->graph->hasDependency('vendor/module3', 'vendor/module2'));
    }

    public function testForModulesIgnoresModulesWithoutModuleFile()
    {
        $modules = new ModuleList();
        $modules->add(new RootModule(new RootModuleFile('vendor/root'), __DIR__));
        $modules->add(new Module(null, __DIR__));

        $this->graph = DependencyGraph::forModules($modules);

        $this->assertTrue($this->graph->hasModuleName('vendor/root'));
    }
}
