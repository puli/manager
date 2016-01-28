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
use Puli\Manager\Conflict\ModuleConflictDetector;
use Puli\Manager\Conflict\OverrideGraph;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleConflictDetectorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var ModuleConflictDetector
     */
    private $detector;

    protected function setUp()
    {
        $this->overrideGraph = new OverrideGraph(array(
            'module1',
            'module2',
            'module3',
        ));
        $this->detector = new ModuleConflictDetector($this->overrideGraph);
    }

    public function testNoConflictIfDifferentClaims()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('B', 'module2');

        $this->assertCount(0, $this->detector->detectConflicts(array('A', 'B')));
    }

    public function testConflictIfSameClaim()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module2');

        $conflicts = $this->detector->detectConflicts(array('A'));

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Conflict\ModuleConflict', $conflicts[0]);
        $this->assertSame('A', $conflicts[0]->getConflictingToken());
        $this->assertSame(array('module1', 'module2'), $conflicts[0]->getModuleNames());
    }

    public function testConflictRemains()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module2');

        $conflicts = $this->detector->detectConflicts(array('A'));

        // Call again
        $this->assertEquals($conflicts, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfConflictingPathNotPased()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('B', 'module1');
        $this->detector->claim('A', 'module2');

        $this->assertCount(0, $this->detector->detectConflicts(array('B')));
    }

    public function testNoConflictIfClaimReleased()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module2');
        $this->detector->release('A', 'module1');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfClaimReleasedAfterDetect()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module2');

        $this->assertCount(1, $this->detector->detectConflicts(array('A')));

        $this->detector->release('A', 'module1');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfEdgeInOverrideGraph()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module2');

        $this->overrideGraph->addEdge('module1', 'module2');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfEdgeAddedAfterDetect()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module2');

        $this->assertCount(1, $this->detector->detectConflicts(array('A')));

        $this->overrideGraph->addEdge('module1', 'module2');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testConflictIfTransitiveOverrideOrder()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module3');

        // Transitive relations ("paths") are not supported. Each pair of
        // conflicting modules must have a relationship defined. Otherwise,
        // if module2 removes the override statement for module1, then
        // module3 and module1 suddenly have a conflict without changing their
        // configuration
        $this->overrideGraph->addEdge('module1', 'module2');
        $this->overrideGraph->addEdge('module2', 'module3');

        $conflicts = $this->detector->detectConflicts(array('A'));

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Conflict\ModuleConflict', $conflicts[0]);
        $this->assertSame('A', $conflicts[0]->getConflictingToken());
        $this->assertSame(array('module1', 'module3'), $conflicts[0]->getModuleNames());
    }

    public function testCheckAllTokensIfNoTokensPassed()
    {
        $this->detector->claim('A', 'module1');
        $this->detector->claim('A', 'module2');

        $conflicts = $this->detector->detectConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\Manager\Conflict\ModuleConflict', $conflicts[0]);
    }
}
