<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Util;

use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Util\TwoDimensionalHashMap;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TwoDimensionalHashMapTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var TwoDimensionalHashMap
     */
    private $map;

    protected function setUp()
    {
        $this->map = new TwoDimensionalHashMap();
    }

    public function testGet()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');

        $this->assertSame('value1', $this->map->get('foo', 'bar'));
        $this->assertSame('value2', $this->map->get('foo', 'baz'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetFailsIfKeysNotSet()
    {
        $this->map->get('foo', 'bar');
    }

    public function testContains()
    {
        $this->assertFalse($this->map->contains('foo'));
        $this->assertFalse($this->map->contains('foo', 'bar'));
        $this->assertFalse($this->map->contains('foo', 'baz'));

        $this->map->set('foo', 'bar', 'value');

        $this->assertTrue($this->map->contains('foo'));
        $this->assertTrue($this->map->contains('foo', 'bar'));
        $this->assertFalse($this->map->contains('foo', 'baz'));
    }

    public function testGetFirst()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');
        $this->map->set('foo', 'bam', 'value3');

        $this->assertSame('value1', $this->map->getFirst('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetFirstFailsIfUnknownPrimaryKey()
    {
        $this->map->getFirst('foo');
    }

    public function testGetLast()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');
        $this->map->set('foo', 'bam', 'value3');

        $this->assertSame('value3', $this->map->getLast('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetLastFailsIfUnknownPrimaryKey()
    {
        $this->map->getLast('foo');
    }

    public function testGetAll()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');
        $this->map->set('foo', 'bam', 'value3');

        $this->assertSame(array(
            'bar' => 'value1',
            'baz' => 'value2',
            'bam' => 'value3',
        ), $this->map->listByPrimaryKey('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetAllFailsIfUnknownPrimaryKey()
    {
        $this->map->listByPrimaryKey('foo');
    }

    public function testGetCount()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');

        $this->assertSame(2, $this->map->getCount('foo'));

        $this->map->set('foo', 'bam', 'value3');

        $this->assertSame(3, $this->map->getCount('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetCountFailsIfUnknownPrimaryKey()
    {
        $this->map->getCount('foo');
    }

    public function testGetSecondaryKeys()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');
        $this->map->set('foo', 'bam', 'value3');

        $this->assertSame(array(
            'bar',
            'baz',
            'bam',
        ), $this->map->getSecondaryKeys('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetSecondaryKeysFailsIfUnknownPrimaryKey()
    {
        $this->map->getSecondaryKeys('foo');
    }

    public function testGetPrimaryKeys()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');
        $this->map->set('foo', 'bam', 'value3');
        $this->map->set('bar', 'boo', 'value4');

        $this->assertSame(array(
            'foo',
            'bar',
        ), $this->map->getPrimaryKeys());
    }

    public function testRemove()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');
        $this->map->set('foo', 'bam', 'value3');
        $this->map->set('bar', 'boo', 'value4');

        $this->map->remove('foo', 'baz');

        $this->assertTrue($this->map->contains('foo'));
        $this->assertFalse($this->map->contains('foo', 'baz'));
        $this->assertSame(2, $this->map->getCount('foo'));
    }

    public function testRemoveLast()
    {
        $this->map->set('foo', 'bar', 'value');

        $this->map->remove('foo', 'bar');

        $this->assertFalse($this->map->contains('foo'));
        $this->assertFalse($this->map->contains('foo', 'bar'));

        $this->setExpectedException('\OutOfBoundsException');

        $this->map->listByPrimaryKey('foo');
    }

    public function testRemoveAll()
    {
        $this->map->set('foo', 'bar', 'value1');
        $this->map->set('foo', 'baz', 'value2');
        $this->map->set('foo', 'bam', 'value3');
        $this->map->set('bar', 'boo', 'value4');

        $this->map->removeAll('foo');

        $this->assertFalse($this->map->contains('foo'));
        $this->assertFalse($this->map->contains('foo', 'bar'));
        $this->assertFalse($this->map->contains('foo', 'baz'));
        $this->assertFalse($this->map->contains('foo', 'bam'));

        $this->setExpectedException('\OutOfBoundsException');

        $this->map->listByPrimaryKey('foo');
    }

    public function testSortPrimaryKeys()
    {
        $this->map->set('c', 'foo', 'value1');
        $this->map->set('c', 'bar', 'value2');
        $this->map->set('a', 'baz', 'value3');
        $this->map->set('b', 'bam', 'value4');

        $this->map->sortPrimaryKeys();

        $this->assertSame(array(
            'a' => array(
                'baz' => 'value3',
            ),
            'b' => array(
                'bam' => 'value4',
            ),
            'c' => array(
                'foo' => 'value1',
                'bar' => 'value2',
            ),
        ), $this->map->toArray());
    }

    public function testSortPrimaryKeysWithOrder()
    {
        $this->map->set('c', 'foo', 'value1');
        $this->map->set('c', 'bar', 'value2');
        $this->map->set('a', 'baz', 'value3');
        $this->map->set('b', 'bam', 'value4');

        $this->map->sortPrimaryKeys(array('b', 'c', 'a', 'd'));

        $this->assertSame(array(
            'b' => array(
                'bam' => 'value4',
            ),
            'c' => array(
                'foo' => 'value1',
                'bar' => 'value2',
            ),
            'a' => array(
                'baz' => 'value3',
            ),
        ), $this->map->toArray());
    }

    public function testSortSecondaryKeys()
    {
        $this->map->set('b', 'foo', 'value1');
        $this->map->set('b', 'bar', 'value2');
        $this->map->set('b', 'baz', 'value3');
        $this->map->set('a', 'bam', 'value4');

        $this->map->sortSecondaryKeys('b');

        $this->assertSame(array(
            'b' => array(
                'bar' => 'value2',
                'baz' => 'value3',
                'foo' => 'value1',
            ),
            'a' => array(
                'bam' => 'value4',
            ),
        ), $this->map->toArray());
    }

    public function testSortSecondaryKeysWithOrder()
    {
        $this->map->set('b', 'foo', 'value1');
        $this->map->set('b', 'bar', 'value2');
        $this->map->set('b', 'baz', 'value3');
        $this->map->set('a', 'bam', 'value4');

        $this->map->sortSecondaryKeys('b', array('baz', 'foo', 'bar', 'bam'));

        $this->assertSame(array(
            'b' => array(
                'baz' => 'value3',
                'foo' => 'value1',
                'bar' => 'value2',
            ),
            'a' => array(
                'bam' => 'value4',
            ),
        ), $this->map->toArray());
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testSortSecondaryKeysFailsIfNotFound()
    {
        $this->map->sortSecondaryKeys('foobar');
    }
}
