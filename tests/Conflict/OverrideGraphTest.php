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
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Conflict\OverrideGraph;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OverrideGraphTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var OverrideGraph
     */
    private $graph;

    protected function setUp()
    {
        $this->graph = new OverrideGraph();
    }

    private function initializeGraph()
    {
        // (p1) → (p2)   →   (p3)
        //       ↗    ↘     ↗
        //   (p5)       (p4)
        //
        //        (p6)

        $this->graph->addModuleName('p1');
        $this->graph->addModuleName('p2');
        $this->graph->addModuleName('p3');
        $this->graph->addModuleName('p4');
        $this->graph->addModuleName('p5');
        $this->graph->addModuleName('p6');

        $this->graph->addEdge('p1', 'p2');
        $this->graph->addEdge('p2', 'p3');
        $this->graph->addEdge('p2', 'p4');
        $this->graph->addEdge('p5', 'p2');
        $this->graph->addEdge('p4', 'p3');
    }

    public function providePaths()
    {
        return array(
            // adjacent
            array('p1', 'p2', array('p1', 'p2')),

            // adjacent, wrong order
            array('p2', 'p1', null),

            // multi-node
            array('p1', 'p3', array('p1', 'p2', 'p3')),

            // multi-node, wrong order
            array('p3', 'p1', null),

            // multi-node, no path
            array('p3', 'p4', null),

            // node without edges
            array('p1', 'p5', null),
            array('p5', 'p1', null),

            // undefined node
            array('p1', 'foo', null),
            array('foo', 'p1', null),
        );
    }

    /**
     * @dataProvider providePaths
     */
    public function testHasPath($from, $to, $path)
    {
        $this->initializeGraph();

        $this->assertSame($path !== null, $this->graph->hasPath($from, $to));
    }

    /**
     * @dataProvider providePaths
     */
    public function testGetPath($from, $to, $path)
    {
        $this->initializeGraph();

        $this->assertSame($path, $this->graph->getPath($from, $to));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddModuleFailsIfAlreadyDefined()
    {
        $this->graph->addModuleName('p1');
        $this->graph->addModuleName('p1');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddEdgeFailsIfLeftModuleDoesNotExist()
    {
        $this->graph->addModuleName('p2');
        $this->graph->addEdge('p1', 'p2');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddEdgeFailsIfRightModuleDoesNotExist()
    {
        $this->graph->addModuleName('p1');
        $this->graph->addEdge('p1', 'p2');
    }

    /**
     * @expectedException \Puli\Manager\Conflict\CyclicDependencyException
     */
    public function testAddEdgeFailsIfCycle()
    {
        $this->graph->addModuleName('p1');
        $this->graph->addModuleName('p2');
        $this->graph->addEdge('p1', 'p2');
        $this->graph->addEdge('p2', 'p1');
    }

    public function testGetSortedModules()
    {
        $this->initializeGraph();

        $this->assertSame(array('p1', 'p5', 'p2', 'p4', 'p3', 'p6'), $this->graph->getSortedModuleNames());
    }

    public function testGetSortedModulesOfSubset()
    {
        $this->initializeGraph();

        $this->assertSame(array('p1', 'p4', 'p3', 'p5', 'p6'), $this->graph->getSortedModuleNames(array('p1', 'p3', 'p4', 'p5', 'p6')));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetSortedModulesExpectsValidModules()
    {
        $this->graph->getSortedModuleNames(array('foo'));
    }

    public function testHasModule()
    {
        $this->assertFalse($this->graph->hasModuleName('p1'));

        $this->graph->addModuleName('p1');

        $this->assertTrue($this->graph->hasModuleName('p1'));
    }

    public function testHasEdge()
    {
        $this->assertFalse($this->graph->hasEdge('p1', 'p2'));
        $this->assertFalse($this->graph->hasEdge('p2', 'p1'));

        $this->graph->addModuleName('p1');
        $this->graph->addModuleName('p2');

        $this->assertFalse($this->graph->hasEdge('p1', 'p2'));
        $this->assertFalse($this->graph->hasEdge('p2', 'p1'));

        $this->graph->addEdge('p1', 'p2');

        $this->assertTrue($this->graph->hasEdge('p1', 'p2'));
        $this->assertFalse($this->graph->hasEdge('p2', 'p1'));
    }

    public function testAddModuleNames()
    {
        $this->assertFalse($this->graph->hasModuleName('p1'));
        $this->assertFalse($this->graph->hasModuleName('p2'));

        $this->graph->addModuleNames(array('p1', 'p2'));

        $this->assertTrue($this->graph->hasModuleName('p1'));
        $this->assertTrue($this->graph->hasModuleName('p2'));
    }

    public function testCreateWithModuleNames()
    {
        $this->graph = new OverrideGraph(array('p1', 'p2'));

        $this->assertTrue($this->graph->hasModuleName('p1'));
        $this->assertTrue($this->graph->hasModuleName('p2'));
    }

    public function testForModules()
    {
        $modules = new ModuleList();
        $modules->add(new RootModule(new RootModuleFile('vendor/root'), __DIR__));
        $modules->add(new Module(new ModuleFile('vendor/module1'), __DIR__));
        $modules->add(new Module(new ModuleFile('vendor/module2'), __DIR__));
        $modules->add(new Module(new ModuleFile('vendor/module3'), __DIR__));

        $modules->get('vendor/module2')->getModuleFile()->addOverriddenModule('vendor/module1');
        $modules->get('vendor/module3')->getModuleFile()->addOverriddenModule('vendor/module1');
        $modules->get('vendor/module3')->getModuleFile()->addOverriddenModule('vendor/module2');

        $this->graph = OverrideGraph::forModules($modules);

        $this->assertTrue($this->graph->hasModuleName('vendor/root'));
        $this->assertTrue($this->graph->hasModuleName('vendor/module1'));
        $this->assertTrue($this->graph->hasModuleName('vendor/module2'));
        $this->assertTrue($this->graph->hasModuleName('vendor/module3'));
        $this->assertTrue($this->graph->hasEdge('vendor/module1', 'vendor/module2'));
        $this->assertTrue($this->graph->hasEdge('vendor/module1', 'vendor/module3'));
        $this->assertTrue($this->graph->hasEdge('vendor/module2', 'vendor/module3'));
    }

    public function testForModulesIgnoresModulesWithoutModuleFile()
    {
        $modules = new ModuleList();
        $modules->add(new RootModule(new RootModuleFile('vendor/root'), __DIR__));
        $modules->add(new Module(null, __DIR__));

        $this->graph = OverrideGraph::forModules($modules);

        $this->assertTrue($this->graph->hasModuleName('vendor/root'));
    }
}
