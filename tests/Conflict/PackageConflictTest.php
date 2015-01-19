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
use Puli\RepositoryManager\Conflict\PackageConflict;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageConflictTest extends PHPUnit_Framework_TestCase
{
    public function testInvolvesPackage()
    {
        $conflict = new PackageConflict('token', array('package1', 'package2'));

        $this->assertTrue($conflict->involvesPackage('package1'));
        $this->assertTrue($conflict->involvesPackage('package2'));
        $this->assertFalse($conflict->involvesPackage('package3'));
    }

    public function testGetOpponents()
    {
        $conflict = new PackageConflict('token', array('package1', 'package2'));

        $this->assertSame(array('package2'), $conflict->getOpponents('package1'));
        $this->assertSame(array('package1'), $conflict->getOpponents('package2'));
        $this->assertSame(array(), $conflict->getOpponents('package3'));
    }
}
