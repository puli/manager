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

        $this->assertNull($this->detector->detectConflict());
    }

    public function testConflictIfSameClaim()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $conflict = $this->detector->detectConflict();

        $this->assertInstanceOf('Puli\RepositoryManager\Conflict\PackageConflict', $conflict);
        $this->assertSame('A', $conflict->getConflictingToken());
        $this->assertSame('package1', $conflict->getPackageName1());
        $this->assertSame('package2', $conflict->getPackageName2());
    }

    public function testConflictRemains()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $conflict = $this->detector->detectConflict();

        // Call again
        $this->assertEquals($conflict, $this->detector->detectConflict());
    }

    public function testNoConflictIfClaimReleased()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');
        $this->detector->release('A', 'package1');

        $this->assertNull($this->detector->detectConflict());
    }

    public function testNoConflictIfClaimReleasedAfterDetect()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $this->assertInstanceOf('Puli\RepositoryManager\Conflict\PackageConflict', $this->detector->detectConflict());

        $this->detector->release('A', 'package1');

        $this->assertNull($this->detector->detectConflict());
    }

    public function testNoConflictIfEdgeInOverrideGraph()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $this->overrideGraph->addEdge('package1', 'package2');

        $this->assertNull($this->detector->detectConflict());
    }

    public function testNoConflictIfEdgeAddedAfterDetect()
    {
        $this->detector->claim('A', 'package1');
        $this->detector->claim('A', 'package2');

        $this->assertInstanceOf('Puli\RepositoryManager\Conflict\PackageConflict', $this->detector->detectConflict());

        $this->overrideGraph->addEdge('package1', 'package2');

        $this->assertNull($this->detector->detectConflict());
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

        $conflict = $this->detector->detectConflict();

        $this->assertInstanceOf('Puli\RepositoryManager\Conflict\PackageConflict', $conflict);
        $this->assertSame('A', $conflict->getConflictingToken());
        $this->assertSame('package1', $conflict->getPackageName1());
        $this->assertSame('package3', $conflict->getPackageName2());
    }
}
