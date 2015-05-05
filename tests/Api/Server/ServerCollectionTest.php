<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Server;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ServerCollectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ServerCollection
     */
    private $collection;

    /**
     * @var Server
     */
    private $server1;

    /**
     * @var Server
     */
    private $server2;

    /**
     * @var Server
     */
    private $server3;

    protected function setUp()
    {
        $this->collection = new ServerCollection();
        $this->server1 = new Server('server1', 'symlink', 'web');
        $this->server2 = new Server('server2', 'rsync', 'ssh://my.cdn.com', 'http://my.cdn.com/%s');
        $this->server3 = new Server('server3', 'ftp', 'ftp://example.com/assets', 'http://example.com/assets/%s');
    }

    public function testCreate()
    {
        $collection = new ServerCollection(array($this->server1, $this->server2));

        $this->assertSame(array(
            'server1' => $this->server1,
            'server2' => $this->server2,
        ), $collection->toArray());
    }

    public function testCreateEmpty()
    {
        $collection = new ServerCollection();

        $this->assertSame(array(), $collection->toArray());
    }

    public function testAdd()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);

        $this->assertSame(array(
            'server1' => $this->server1,
            'server2' => $this->server2,
        ), $this->collection->toArray());
    }

    public function testAddIgnoresDuplicates()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server1);

        $this->assertSame(array(
            'server1' => $this->server1,
        ), $this->collection->toArray());
    }

    public function testMerge()
    {
        $this->collection->add($this->server1);
        $this->collection->merge(array($this->server2, $this->server3));

        $this->assertSame(array(
            'server1' => $this->server1,
            'server2' => $this->server2,
            'server3' => $this->server3,
        ), $this->collection->toArray());
    }

    public function testReplace()
    {
        $this->collection->add($this->server1);
        $this->collection->replace(array($this->server2, $this->server3));

        $this->assertSame(array(
            'server2' => $this->server2,
            'server3' => $this->server3,
        ), $this->collection->toArray());
    }

    public function testRemove()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);
        $this->collection->remove('server1');

        $this->assertSame(array(
            'server2' => $this->server2,
        ), $this->collection->toArray());
    }

    public function testRemoveIgnoresNonExisting()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);
        $this->collection->remove('foobar');

        $this->assertSame(array(
            'server1' => $this->server1,
            'server2' => $this->server2,
        ), $this->collection->toArray());
    }

    public function testClear()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);
        $this->collection->clear();

        $this->assertSame(array(), $this->collection->toArray());
    }

    public function testGet()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);

        $this->assertSame($this->server1, $this->collection->get('server1'));
        $this->assertSame($this->server2, $this->collection->get('server2'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Server\NoSuchServerException
     * @expectedExceptionMessage foobar
     */
    public function testGetFailsIfNotFound()
    {
        $this->collection->get('foobar');
    }

    public function testContains()
    {
        $this->collection->add($this->server1);

        $this->assertTrue($this->collection->contains('server1'));
        $this->assertFalse($this->collection->contains('foobar'));
    }

    public function testIsEmpty()
    {
        $this->assertTrue($this->collection->isEmpty());
        $this->collection->add($this->server1);
        $this->assertFalse($this->collection->isEmpty());
        $this->collection->clear();
        $this->assertTrue($this->collection->isEmpty());
    }

    public function testCount()
    {
        $this->assertSame(0, $this->collection->count());
        $this->collection->add($this->server1);
        $this->assertSame(1, $this->collection->count());
        $this->collection->add($this->server2);
        $this->assertSame(2, $this->collection->count());
    }

    public function testIterate()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);

        $this->assertSame(array(
            'server1' => $this->server1,
            'server2' => $this->server2,
        ), iterator_to_array($this->collection));
    }

    public function testArrayAccess()
    {
        $this->collection[] = $this->server1;
        $this->collection[] = $this->server2;

        $this->assertSame(array(
            'server1' => $this->server1,
            'server2' => $this->server2,
        ), $this->collection->toArray());
        $this->assertSame($this->server1, $this->collection['server1']);
        $this->assertSame($this->server2, $this->collection['server2']);
        $this->assertTrue(isset($this->collection['server1']));
        $this->assertFalse(isset($this->collection['foobar']));

        unset($this->collection['server2']);
        unset($this->collection['foobar']);

        $this->assertSame(array(
            'server1' => $this->server1,
        ), $this->collection->toArray());
    }

    /**
     * @expectedException \LogicException
     */
    public function testArrayAccessFailsIfKeyIsPassed()
    {
        $this->collection['key'] = $this->server1;
    }

    public function testGetServerNames()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);

        $this->assertSame(array('server1', 'server2'), $this->collection->getServerNames());
    }

    public function testAddSetsDefaultServer()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);

        $this->assertSame($this->server1, $this->collection->getDefaultServer());
    }

    public function testRemoveUpdatesDefaultServer()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);
        $this->collection->remove('server1');

        $this->assertSame($this->server2, $this->collection->getDefaultServer());

        $this->collection->remove('server2');
        $this->collection->add($this->server1);

        $this->assertSame($this->server1, $this->collection->getDefaultServer());
    }

    public function testClearResetsDefaultServer()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);
        $this->collection->clear();
        $this->collection->add($this->server2);

        $this->assertSame($this->server2, $this->collection->getDefaultServer());
    }

    public function testSetDefaultServer()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);
        $this->collection->setDefaultServer('server2');

        $this->assertSame($this->server2, $this->collection->getDefaultServer());
    }

    /**
     * @expectedException \Puli\Manager\Api\Server\NoSuchServerException
     * @expectedExceptionMessage foobar
     */
    public function testSetDefaultServerFailsIfNotFound()
    {
        $this->collection->setDefaultServer('foobar');
    }

    /**
     * @expectedException \Puli\Manager\Api\Server\NoSuchServerException
     */
    public function testGetDefaultServerFailsIfEmpty()
    {
        $this->collection->getDefaultServer();
    }

    public function testGetWithDefaultServer()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);

        $this->assertSame($this->server1, $this->collection->get(Server::DEFAULT_SERVER));

        $this->collection->setDefaultServer('server2');

        $this->assertSame($this->server2, $this->collection->get(Server::DEFAULT_SERVER));
    }

    public function testRemoveWithDefaultServer()
    {
        $this->collection->add($this->server1);
        $this->collection->add($this->server2);

        $this->collection->remove(Server::DEFAULT_SERVER);

        $this->assertSame($this->server2, $this->collection->get(Server::DEFAULT_SERVER));
    }

    public function testContainsWithDefaultServer()
    {
        $this->assertFalse($this->collection->contains(Server::DEFAULT_SERVER));
        $this->collection->add($this->server1);
        $this->assertTrue($this->collection->contains(Server::DEFAULT_SERVER));
    }
}
