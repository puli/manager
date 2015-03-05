<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Api\Discovery;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Api\Discovery\BindingTypeCriteria;
use Puli\RepositoryManager\Api\Discovery\BindingTypeState;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingTypeCriteriaTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BindingTypeCriteria
     */
    private $criteria;

    protected function setUp()
    {
        $this->criteria = new BindingTypeCriteria();
    }

    public function testAddPackageName()
    {
        $this->criteria->addPackageName('package1');
        $this->criteria->addPackageName('package2');

        $this->assertSame(array('package1', 'package2'), $this->criteria->getPackageNames());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddPackageNameFailsIfNull()
    {
        $this->criteria->addPackageName(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddPackageNameFailsIfEmpty()
    {
        $this->criteria->addPackageName('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddPackageNameFailsIfNoString()
    {
        $this->criteria->addPackageName(1234);
    }

    public function testAddPackageNames()
    {
        $this->criteria->addPackageName('package1');
        $this->criteria->addPackageNames(array('package2', 'package3'));

        $this->assertSame(array('package1', 'package2', 'package3'), $this->criteria->getPackageNames());
    }

    public function testSetPackageNames()
    {
        $this->criteria->addPackageName('package1');
        $this->criteria->setPackageNames(array('package2', 'package3'));

        $this->assertSame(array('package2', 'package3'), $this->criteria->getPackageNames());
    }

    public function testRemovePackageName()
    {
        $this->criteria->addPackageName('package1');
        $this->criteria->addPackageName('package2');
        $this->criteria->removePackageName('package1');

        $this->assertSame(array('package2'), $this->criteria->getPackageNames());
    }

    public function testClearPackageNames()
    {
        $this->criteria->addPackageName('package1');
        $this->criteria->addPackageName('package2');
        $this->criteria->clearPackageNames();

        $this->assertSame(array(), $this->criteria->getPackageNames());
    }

    public function testMatchPackageName()
    {
        $this->criteria->addPackageName('package1');
        $this->criteria->addPackageName('package2');

        $this->assertTrue($this->criteria->matchPackageName('package1'));
        $this->assertTrue($this->criteria->matchPackageName('package2'));
        $this->assertFalse($this->criteria->matchPackageName('package3'));
    }

    public function testMatchPackageNameIfNoneSet()
    {
        $this->assertTrue($this->criteria->matchPackageName('package1'));
        $this->assertTrue($this->criteria->matchPackageName('package2'));
        $this->assertTrue($this->criteria->matchPackageName('package3'));
    }

    public function testAddState()
    {
        $this->criteria->addState(BindingTypeState::ENABLED);
        $this->criteria->addState(BindingTypeState::DUPLICATE);

        $this->assertSame(array(BindingTypeState::ENABLED, BindingTypeState::DUPLICATE), $this->criteria->getStates());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddStateFailsIfInvalid()
    {
        $this->criteria->addState(0);
    }

    public function testAddStates()
    {
        $this->criteria->addState(BindingTypeState::ENABLED);
        $this->criteria->addStates(array(BindingTypeState::DUPLICATE));

        $this->assertSame(array(BindingTypeState::ENABLED, BindingTypeState::DUPLICATE), $this->criteria->getStates());
    }

    public function testSetStates()
    {
        $this->criteria->addState(BindingTypeState::ENABLED);
        $this->criteria->setStates(array(BindingTypeState::DUPLICATE));

        $this->assertSame(array(BindingTypeState::DUPLICATE), $this->criteria->getStates());
    }

    public function testRemoveState()
    {
        $this->criteria->addState(BindingTypeState::ENABLED);
        $this->criteria->addState(BindingTypeState::DUPLICATE);
        $this->criteria->removeState(BindingTypeState::ENABLED);

        $this->assertSame(array(BindingTypeState::DUPLICATE), $this->criteria->getStates());
    }

    public function testClearStates()
    {
        $this->criteria->addState(BindingTypeState::ENABLED);
        $this->criteria->addState(BindingTypeState::DUPLICATE);
        $this->criteria->clearStates();

        $this->assertSame(array(), $this->criteria->getStates());
    }

    public function testMatchState()
    {
        $this->criteria->addState(BindingTypeState::ENABLED);
        $this->criteria->addState(BindingTypeState::DUPLICATE);

        $this->assertTrue($this->criteria->matchState(BindingTypeState::ENABLED));
        $this->assertTrue($this->criteria->matchState(BindingTypeState::DUPLICATE));
        $this->assertFalse($this->criteria->matchState(42));
    }

    public function testMatchStateIfNoneSet()
    {
        $this->assertTrue($this->criteria->matchState(BindingTypeState::ENABLED));
        $this->assertTrue($this->criteria->matchState(BindingTypeState::DUPLICATE));
        $this->assertTrue($this->criteria->matchState(42));
    }
}
