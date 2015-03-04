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
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\BindingCriteria;
use Rhumsaa\Uuid\Uuid;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingCriteriaTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BindingCriteria
     */
    private $criteria;

    protected function setUp()
    {
        $this->criteria = new BindingCriteria();
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
        $this->criteria->addState(BindingState::ENABLED);
        $this->criteria->addState(BindingState::DISABLED);

        $this->assertSame(array(BindingState::ENABLED, BindingState::DISABLED), $this->criteria->getStates());
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
        $this->criteria->addState(BindingState::ENABLED);
        $this->criteria->addStates(array(BindingState::DISABLED, BindingState::UNDECIDED));

        $this->assertSame(array(BindingState::ENABLED, BindingState::DISABLED, BindingState::UNDECIDED), $this->criteria->getStates());
    }

    public function testSetStates()
    {
        $this->criteria->addState(BindingState::ENABLED);
        $this->criteria->setStates(array(BindingState::DISABLED, BindingState::UNDECIDED));

        $this->assertSame(array(BindingState::DISABLED, BindingState::UNDECIDED), $this->criteria->getStates());
    }

    public function testRemoveState()
    {
        $this->criteria->addState(BindingState::ENABLED);
        $this->criteria->addState(BindingState::DISABLED);
        $this->criteria->removeState(BindingState::ENABLED);

        $this->assertSame(array(BindingState::DISABLED), $this->criteria->getStates());
    }

    public function testClearStates()
    {
        $this->criteria->addState(BindingState::ENABLED);
        $this->criteria->addState(BindingState::DISABLED);
        $this->criteria->clearStates();

        $this->assertSame(array(), $this->criteria->getStates());
    }

    public function testMatchState()
    {
        $this->criteria->addState(BindingState::ENABLED);
        $this->criteria->addState(BindingState::DISABLED);

        $this->assertTrue($this->criteria->matchState(BindingState::ENABLED));
        $this->assertTrue($this->criteria->matchState(BindingState::DISABLED));
        $this->assertFalse($this->criteria->matchState(BindingState::UNDECIDED));
    }

    public function testMatchStateIfNoneSet()
    {
        $this->assertTrue($this->criteria->matchState(BindingState::ENABLED));
        $this->assertTrue($this->criteria->matchState(BindingState::DISABLED));
        $this->assertTrue($this->criteria->matchState(BindingState::UNDECIDED));
    }

    public function testSetUuidPrefix()
    {
        $this->criteria->setUuidPrefix('abcd');

        $this->assertSame('abcd', $this->criteria->getUuidPrefix());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetUuidPrefixFailsIfNull()
    {
        $this->criteria->setUuidPrefix(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetUuidPrefixFailsIfEmpty()
    {
        $this->criteria->setUuidPrefix('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetUuidPrefixFailsIfNoString()
    {
        $this->criteria->setUuidPrefix(1234);
    }

    public function testClearUuidPrefix()
    {
        $this->criteria->setUuidPrefix('abcd');
        $this->criteria->clearUuidPrefix();

        $this->assertNull($this->criteria->getUuidPrefix());
    }

    public function testMatchUuidPrefix()
    {
        $this->criteria->setUuidPrefix('abcd');

        $this->assertTrue($this->criteria->matchUuid(Uuid::fromString('abcdb814-9dad-11d1-80b4-00c04fd430c8')));
        $this->assertFalse($this->criteria->matchUuid(Uuid::fromString('abceb814-9dad-11d1-80b4-00c04fd430c8')));
    }

    public function testMatchUuidPrefixIfNoneSet()
    {
        $this->assertTrue($this->criteria->matchUuid(Uuid::fromString('abcdb814-9dad-11d1-80b4-00c04fd430c8')));
        $this->assertTrue($this->criteria->matchUuid(Uuid::fromString('abceb814-9dad-11d1-80b4-00c04fd430c8')));
    }

    public function testSetQuery()
    {
        $this->criteria->setQuery('/path');

        $this->assertSame('/path', $this->criteria->getQuery());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetQueryFailsIfNull()
    {
        $this->criteria->setQuery(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetQueryFailsIfEmpty()
    {
        $this->criteria->setQuery('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetQueryFailsIfNoString()
    {
        $this->criteria->setQuery(1234);
    }

    public function testClearQuery()
    {
        $this->criteria->setQuery('/path');
        $this->criteria->clearQuery();

        $this->assertNull($this->criteria->getQuery());
    }

    public function testMatchQuery()
    {
        $this->criteria->setQuery('/path');

        $this->assertTrue($this->criteria->matchQuery('/path'));
        $this->assertFalse($this->criteria->matchQuery('/path/nested'));
    }

    public function testMatchQueryIfNoneSet()
    {
        $this->assertTrue($this->criteria->matchQuery('/path'));
        $this->assertTrue($this->criteria->matchQuery('/path/nested'));
    }

    public function testSetLanguage()
    {
        $this->criteria->setLanguage('glob');

        $this->assertSame('glob', $this->criteria->getLanguage());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetLanguageFailsIfNull()
    {
        $this->criteria->setLanguage(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetLanguageFailsIfEmpty()
    {
        $this->criteria->setLanguage('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetLanguageFailsIfNoString()
    {
        $this->criteria->setLanguage(1234);
    }

    public function testClearLanguage()
    {
        $this->criteria->setLanguage('glob');
        $this->criteria->clearLanguage();

        $this->assertNull($this->criteria->getLanguage());
    }

    public function testMatchLanguage()
    {
        $this->criteria->setLanguage('glob');

        $this->assertTrue($this->criteria->matchLanguage('glob'));
        $this->assertFalse($this->criteria->matchLanguage('xpath'));
    }

    public function testMatchLanguageIfNoneSet()
    {
        $this->assertTrue($this->criteria->matchLanguage('glob'));
        $this->assertTrue($this->criteria->matchLanguage('xpath'));
    }

    public function testSetTypeName()
    {
        $this->criteria->setTypeName('type1');

        $this->assertSame('type1', $this->criteria->getTypeName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeNameFailsIfNull()
    {
        $this->criteria->setTypeName(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeNameFailsIfEmpty()
    {
        $this->criteria->setTypeName('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTypeNameFailsIfNoString()
    {
        $this->criteria->setTypeName(1234);
    }

    public function testClearTypeName()
    {
        $this->criteria->setTypeName('type1');
        $this->criteria->clearTypeName();

        $this->assertNull($this->criteria->getTypeName());
    }

    public function testMatchTypeName()
    {
        $this->criteria->setTypeName('type1');

        $this->assertTrue($this->criteria->matchTypeName('type1'));
        $this->assertFalse($this->criteria->matchTypeName('type2'));
    }

    public function testMatchTypeNameIfNoneSet()
    {
        $this->assertTrue($this->criteria->matchTypeName('type1'));
        $this->assertTrue($this->criteria->matchTypeName('type2'));
    }
}
