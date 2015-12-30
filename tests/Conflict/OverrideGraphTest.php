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
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
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

        $this->graph->addPackageName('p1');
        $this->graph->addPackageName('p2');
        $this->graph->addPackageName('p3');
        $this->graph->addPackageName('p4');
        $this->graph->addPackageName('p5');
        $this->graph->addPackageName('p6');

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
    public function testAddPackageFailsIfAlreadyDefined()
    {
        $this->graph->addPackageName('p1');
        $this->graph->addPackageName('p1');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddEdgeFailsIfLeftPackageDoesNotExist()
    {
        $this->graph->addPackageName('p2');
        $this->graph->addEdge('p1', 'p2');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddEdgeFailsIfRightPackageDoesNotExist()
    {
        $this->graph->addPackageName('p1');
        $this->graph->addEdge('p1', 'p2');
    }

    /**
     * @expectedException \Puli\Manager\Conflict\CyclicDependencyException
     */
    public function testAddEdgeFailsIfCycle()
    {
        $this->graph->addPackageName('p1');
        $this->graph->addPackageName('p2');
        $this->graph->addEdge('p1', 'p2');
        $this->graph->addEdge('p2', 'p1');
    }

    public function testGetSortedPackages()
    {
        $this->initializeGraph();

        $this->assertSame(array('p1', 'p5', 'p2', 'p4', 'p3', 'p6'), $this->graph->getSortedPackageNames());
    }

    public function testGetSortedPackagesOfSubset()
    {
        $this->initializeGraph();

        $this->assertSame(array('p1', 'p4', 'p3', 'p5', 'p6'), $this->graph->getSortedPackageNames(array('p1', 'p3', 'p4', 'p5', 'p6')));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetSortedPackagesExpectsValidPackages()
    {
        $this->graph->getSortedPackageNames(array('foo'));
    }

    public function testHasPackage()
    {
        $this->assertFalse($this->graph->hasPackageName('p1'));

        $this->graph->addPackageName('p1');

        $this->assertTrue($this->graph->hasPackageName('p1'));
    }

    public function testHasEdge()
    {
        $this->assertFalse($this->graph->hasEdge('p1', 'p2'));
        $this->assertFalse($this->graph->hasEdge('p2', 'p1'));

        $this->graph->addPackageName('p1');
        $this->graph->addPackageName('p2');

        $this->assertFalse($this->graph->hasEdge('p1', 'p2'));
        $this->assertFalse($this->graph->hasEdge('p2', 'p1'));

        $this->graph->addEdge('p1', 'p2');

        $this->assertTrue($this->graph->hasEdge('p1', 'p2'));
        $this->assertFalse($this->graph->hasEdge('p2', 'p1'));
    }

    public function testAddPackageNames()
    {
        $this->assertFalse($this->graph->hasPackageName('p1'));
        $this->assertFalse($this->graph->hasPackageName('p2'));

        $this->graph->addPackageNames(array('p1', 'p2'));

        $this->assertTrue($this->graph->hasPackageName('p1'));
        $this->assertTrue($this->graph->hasPackageName('p2'));
    }

    public function testCreateWithPackageNames()
    {
        $this->graph = new OverrideGraph(array('p1', 'p2'));

        $this->assertTrue($this->graph->hasPackageName('p1'));
        $this->assertTrue($this->graph->hasPackageName('p2'));
    }

    public function testForPackages()
    {
        $packages = new PackageCollection();
        $packages->add(new RootPackage(new RootPackageFile('vendor/root'), __DIR__));
        $packages->add(new Package(new PackageFile('vendor/package1'), __DIR__));
        $packages->add(new Package(new PackageFile('vendor/package2'), __DIR__));
        $packages->add(new Package(new PackageFile('vendor/package3'), __DIR__));

        $packages->get('vendor/package2')->getPackageFile()->addOverriddenPackage('vendor/package1');
        $packages->get('vendor/package3')->getPackageFile()->addOverriddenPackage('vendor/package1');
        $packages->get('vendor/package3')->getPackageFile()->addOverriddenPackage('vendor/package2');

        $this->graph = OverrideGraph::forPackages($packages);

        $this->assertTrue($this->graph->hasPackageName('vendor/root'));
        $this->assertTrue($this->graph->hasPackageName('vendor/package1'));
        $this->assertTrue($this->graph->hasPackageName('vendor/package2'));
        $this->assertTrue($this->graph->hasPackageName('vendor/package3'));
        $this->assertTrue($this->graph->hasEdge('vendor/package1', 'vendor/package2'));
        $this->assertTrue($this->graph->hasEdge('vendor/package1', 'vendor/package3'));
        $this->assertTrue($this->graph->hasEdge('vendor/package2', 'vendor/package3'));
    }

    public function testForPackagesIgnoresPackagesWithoutPackageFile()
    {
        $packages = new PackageCollection();
        $packages->add(new RootPackage(new RootPackageFile('vendor/root'), __DIR__));
        $packages->add(new Package(null, __DIR__));

        $this->graph = OverrideGraph::forPackages($packages);

        $this->assertTrue($this->graph->hasPackageName('vendor/root'));
    }
}
