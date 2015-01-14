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
use Puli\RepositoryManager\Util\CompositeKeyStore;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeKeyStoreTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var CompositeKeyStore
     */
    private $store;

    protected function setUp()
    {
        $this->store = new CompositeKeyStore();
    }

    public function testGet()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');

        $this->assertSame('value1', $this->store->get('foo', 'bar'));
        $this->assertSame('value2', $this->store->get('foo', 'baz'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetFailsIfKeysNotSet()
    {
        $this->store->get('foo', 'bar');
    }

    public function testContains()
    {
        $this->assertFalse($this->store->contains('foo'));
        $this->assertFalse($this->store->contains('foo', 'bar'));
        $this->assertFalse($this->store->contains('foo', 'baz'));

        $this->store->set('foo', 'bar', 'value');

        $this->assertTrue($this->store->contains('foo'));
        $this->assertTrue($this->store->contains('foo', 'bar'));
        $this->assertFalse($this->store->contains('foo', 'baz'));
    }

    public function testGetFirst()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');
        $this->store->set('foo', 'bam', 'value3');

        $this->assertSame('value1', $this->store->getFirst('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetFirstFailsIfUnknownPrimaryKey()
    {
        $this->store->getFirst('foo');
    }

    public function testGetLast()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');
        $this->store->set('foo', 'bam', 'value3');

        $this->assertSame('value3', $this->store->getLast('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetLastFailsIfUnknownPrimaryKey()
    {
        $this->store->getLast('foo');
    }

    public function testGetAll()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');
        $this->store->set('foo', 'bam', 'value3');

        $this->assertSame(array(
            'bar' => 'value1',
            'baz' => 'value2',
            'bam' => 'value3',
        ), $this->store->getAll('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetAllFailsIfUnknownPrimaryKey()
    {
        $this->store->getAll('foo');
    }

    public function testGetCount()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');

        $this->assertSame(2, $this->store->getCount('foo'));

        $this->store->set('foo', 'bam', 'value3');

        $this->assertSame(3, $this->store->getCount('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetCountFailsIfUnknownPrimaryKey()
    {
        $this->store->getCount('foo');
    }

    public function testGetSecondaryKeys()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');
        $this->store->set('foo', 'bam', 'value3');

        $this->assertSame(array(
            'bar',
            'baz',
            'bam',
        ), $this->store->getSecondaryKeys('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetSecondaryKeysFailsIfUnknownPrimaryKey()
    {
        $this->store->getSecondaryKeys('foo');
    }

    public function testGetPrimaryKeys()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');
        $this->store->set('foo', 'bam', 'value3');
        $this->store->set('bar', 'boo', 'value4');

        $this->assertSame(array(
            'foo',
            'bar',
        ), $this->store->getPrimaryKeys());
    }

    public function testRemove()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');
        $this->store->set('foo', 'bam', 'value3');
        $this->store->set('bar', 'boo', 'value4');

        $this->store->remove('foo', 'baz');

        $this->assertTrue($this->store->contains('foo'));
        $this->assertFalse($this->store->contains('foo', 'baz'));
        $this->assertSame(2, $this->store->getCount('foo'));
    }

    public function testRemoveLast()
    {
        $this->store->set('foo', 'bar', 'value');

        $this->store->remove('foo', 'bar');

        $this->assertFalse($this->store->contains('foo'));
        $this->assertFalse($this->store->contains('foo', 'bar'));

        $this->setExpectedException('\OutOfBoundsException');

        $this->store->getAll('foo');
    }

    public function testRemoveAll()
    {
        $this->store->set('foo', 'bar', 'value1');
        $this->store->set('foo', 'baz', 'value2');
        $this->store->set('foo', 'bam', 'value3');
        $this->store->set('bar', 'boo', 'value4');

        $this->store->removeAll('foo');

        $this->assertFalse($this->store->contains('foo'));
        $this->assertFalse($this->store->contains('foo', 'bar'));
        $this->assertFalse($this->store->contains('foo', 'baz'));
        $this->assertFalse($this->store->contains('foo', 'bam'));

        $this->setExpectedException('\OutOfBoundsException');

        $this->store->getAll('foo');
    }

}
