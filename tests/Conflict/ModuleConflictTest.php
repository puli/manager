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
use Puli\Manager\Conflict\ModuleConflict;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleConflictTest extends PHPUnit_Framework_TestCase
{
    public function testInvolvesModule()
    {
        $conflict = new ModuleConflict('token', array('module1', 'module2'));

        $this->assertTrue($conflict->involvesModule('module1'));
        $this->assertTrue($conflict->involvesModule('module2'));
        $this->assertFalse($conflict->involvesModule('module3'));
    }

    public function testGetOpponents()
    {
        $conflict = new ModuleConflict('token', array('module1', 'module2'));

        $this->assertSame(array('module2'), $conflict->getOpponents('module1'));
        $this->assertSame(array('module1'), $conflict->getOpponents('module2'));
        $this->assertSame(array(), $conflict->getOpponents('module3'));
    }
}
