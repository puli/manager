<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Util;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Util\DistinguishedName;
use stdClass;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DistinguishedNameTest extends PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $dn = new DistinguishedName();
        $dn->add('cn', 'Manager');
        $dn->add('ou', 'Sales');

        $this->assertSame('Manager', $dn->get('cn'));
        $this->assertSame('Sales', $dn->get('ou'));
        $this->assertSame(array('cn' => 'Manager', 'ou' => 'Sales'), $dn->toArray());
    }

    public function getValidNames()
    {
        return array(
            array('cn1'),
            array('c-n'),
        );
    }

    /**
     * @dataProvider getValidNames
     */
    public function testAddAcceptsValidNames($name)
    {
        $dn = new DistinguishedName();
        $dn->add($name, 'Manager');

        $this->assertSame('Manager', $dn->get($name));
    }

    public function getInvalidNames()
    {
        return array(
            array('ćn'),
            array('c,n'),
            array('1cn'),
            array('cö'),
            array('c='),
        );
    }

    /**
     * @dataProvider getInvalidNames
     * @expectedException \InvalidArgumentException
     */
    public function testAddRejectsInvalidNames($name)
    {
        $dn = new DistinguishedName();
        $dn->add($name, 'Manager');
    }

    public function getInvalidValues()
    {
        return array(
            array(1234),
            array(true),
            array(null),
            array(new stdClass()),
        );
    }

    /**
     * @dataProvider getInvalidValues
     * @expectedException \InvalidArgumentException
     */
    public function testAddRejectsInvalidValues($value)
    {
        $dn = new DistinguishedName();
        $dn->add('cn', $value);
    }

    public function testMerge()
    {
        $dn = new DistinguishedName();
        $dn->add('cn', 'Manager');
        $dn->merge(array('ou' => 'Sales', 'o' => 'Company'));

        $this->assertSame('Manager', $dn->get('cn'));
        $this->assertSame('Sales', $dn->get('ou'));
        $this->assertSame('Company', $dn->get('o'));
        $this->assertSame(array('cn' => 'Manager', 'ou' => 'Sales', 'o' => 'Company'), $dn->toArray());
    }

    public function testCreateWithAttributes()
    {
        $dn = new DistinguishedName(array('cn' => 'Manager', 'ou' => 'Sales'));

        $this->assertSame('Manager', $dn->get('cn'));
        $this->assertSame('Sales', $dn->get('ou'));
        $this->assertSame(array('cn' => 'Manager', 'ou' => 'Sales'), $dn->toArray());
    }

    public function testRemove()
    {
        $dn = new DistinguishedName();
        $dn->merge(array('cn' => 'Manager', 'ou' => 'Sales', 'o' => 'Company'));
        $dn->remove('o');

        $this->assertSame('Manager', $dn->get('cn'));
        $this->assertSame('Sales', $dn->get('ou'));
        $this->assertSame(array('cn' => 'Manager', 'ou' => 'Sales'), $dn->toArray());
    }

    public function testHas()
    {
        $dn = new DistinguishedName();

        $this->assertFalse($dn->has('cn'));

        $dn->add('cn', 'Manager');

        $this->assertTrue($dn->has('cn'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetFailsIfUnknownName()
    {
        $dn = new DistinguishedName();
        $dn->get('cn');
    }

    public function testToString()
    {
        $dn = new DistinguishedName();
        $dn->add('cn', 'Manager');
        $dn->add('ou', 'Sales');
        $dn->add('o', 'Company');

        $this->assertSame('cn="Manager",ou="Sales",o="Company"', $dn->toString());
    }

    public function getEscapedValues()
    {
        return array(
            array('cn', ' Manager', 'cn=" Manager"'),
            array('cn', 'Mana\\ger', 'cn="Mana\\\\ger"'),
            array('cn', '"Manager"', 'cn="\\"Manager\\""'),
            array('cn', '', 'cn=""'),
        );
    }

    /**
     * @dataProvider getEscapedValues
     */
    public function testToStringEscapesProperly($name, $value, $string)
    {
        $dn = new DistinguishedName();
        $dn->add($name, $value);

        $this->assertSame($string, $dn->toString());
    }
}
