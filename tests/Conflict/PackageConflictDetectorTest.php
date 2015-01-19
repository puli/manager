<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Conflict;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Conflict\OverrideGraph;
use Puli\RepositoryManager\Conflict\PackageConflictDetector;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConflictDetectorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Puli\RepositoryManager\Conflict\OverrideGraph
     */
    private $overrideGraph;

    /**
     * @var PackageConflictDetector
     */
    private $detector;

    protected function setUp()
    {
        $this->overrideGraph = new OverrideGraph(array(
            'package1',
            'package2',
            'package3',
        ));
        $this->detector = new PackageConflictDetector($this->overrideGraph);
    }

    public function testNoConflictIfDifferentClaims()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('B', 'package2');

        $this->assertCount(0, $this->detector->detectConflicts(array('A', 'B')));
    }

    public function testConflictIfSameClaim()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $conflicts = $this->detector->detectConflicts(array('A'));

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\RepositoryManager\Conflict\PackageConflict', $conflicts[0]);
        $this->assertSame('A', $conflicts[0]->getConflictingToken());
        $this->assertSame(array('package1', 'package2'), $conflicts[0]->getPackageNames());
    }

    public function testConflictRemains()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $conflicts = $this->detector->detectConflicts(array('A'));

        // Call again
        $this->assertEquals($conflicts, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfConflictingPathNotPased()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('B', 'package1');
        $this->detector->claim('A', 'package2');

        $this->assertCount(0, $this->detector->detectConflicts(array('B')));
    }

    public function testNoConflictIfClaimReleased()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');
        $this->detector->release('A', 'package1');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfClaimReleasedAfterDetect()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $this->assertCount(1, $this->detector->detectConflicts(array('A')));

        $this->detector->release('A', 'package1');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfEdgeInOverrideGraph()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $this->overrideGraph->addEdge('package1', 'package2');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testNoConflictIfEdgeAddedAfterDetect()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $this->assertCount(1, $this->detector->detectConflicts(array('A')));

        $this->overrideGraph->addEdge('package1', 'package2');

        $this->assertCount(0, $this->detector->detectConflicts(array('A')));
    }

    public function testConflictIfTransitiveOverrideOrder()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package3');

        // Transitive relations ("paths") are not supported. Each pair of
        // conflicting packages must have a relationship defined. Otherwise,
        // if package2 removes the override statement for package1, then
        // package3 and package1 suddenly have a conflict without changing their
        // configuration
        $this->overrideGraph->addEdge('package1', 'package2');
        $this->overrideGraph->addEdge('package2', 'package3');

        $conflicts = $this->detector->detectConflicts(array('A'));

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\RepositoryManager\Conflict\PackageConflict', $conflicts[0]);
        $this->assertSame('A', $conflicts[0]->getConflictingToken());
        $this->assertSame(array('package1', 'package3'), $conflicts[0]->getPackageNames());
    }

    public function testCheckAllTokensIfNoTokensPassed()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $conflicts = $this->detector->detectConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertInstanceOf('Puli\RepositoryManager\Conflict\PackageConflict', $conflicts[0]);
    }
}
