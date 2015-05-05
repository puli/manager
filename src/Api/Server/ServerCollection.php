<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Server;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use LogicException;

/**
 * A collection of {@link Server} instances.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ServerCollection implements IteratorAggregate, ArrayAccess, Countable
{
    /**
     * @var Server[]
     */
    private $servers = array();

    /**
     * @var Server
     */
    private $defaultServer;

    /**
     * Creates the collection.
     *
     * @param Server[] $servers The servers to initially fill into the
     *                               collection.
     */
    public function __construct(array $servers = array())
    {
        $this->merge($servers);
    }

    /**
     * Adds a server to the collection.
     *
     * @param Server $server The server to add.
     */
    public function add(Server $server)
    {
        $this->servers[$server->getName()] = $server;

        if (!$this->defaultServer) {
            $this->defaultServer = $server;
        }
    }

    /**
     * Returns the server with the given name.
     *
     * @param string $serverName The server name.
     *
     * @return Server The server.
     *
     * @throws NoSuchServerException If the server does not exist.
     */
    public function get($serverName)
    {
        if (Server::DEFAULT_SERVER === $serverName) {
            return $this->getDefaultServer();
        }

        if (!isset($this->servers[$serverName])) {
            throw NoSuchServerException::forServerName($serverName);
        }

        return $this->servers[$serverName];
    }

    /**
     * Removes a server from the collection.
     *
     * If the server does not exist, this method does nothing.
     *
     * @param string $serverName The server name.
     */
    public function remove($serverName)
    {
        if (Server::DEFAULT_SERVER === $serverName && $this->defaultServer) {
            $serverName = $this->defaultServer->getName();
        }

        unset($this->servers[$serverName]);

        if ($this->defaultServer && $serverName === $this->defaultServer->getName()) {
            $this->defaultServer = $this->servers ? reset($this->servers) : null;
        }
    }

    /**
     * Returns whether a server exists.
     *
     * @param string $serverName The server name.
     *
     * @return bool Whether the server exists.
     */
    public function contains($serverName)
    {
        if (Server::DEFAULT_SERVER === $serverName) {
            return null !== $this->defaultServer;
        }

        return isset($this->servers[$serverName]);
    }

    /**
     * Removes all servers from the collection.
     */
    public function clear()
    {
        $this->servers = array();
        $this->defaultServer = null;
    }

    /**
     * Returns the names of all servers in the collection.
     *
     * @return string[] The server names.
     */
    public function getServerNames()
    {
        return array_keys($this->servers);
    }

    /**
     * Replaces the collection contents with the given servers.
     *
     * @param Server[] $servers The install servers to set.
     */
    public function replace(array $servers)
    {
        $this->clear();
        $this->merge($servers);
    }

    /**
     * Merges the given servers into the collection.
     *
     * @param Server[] $servers The install servers to add.
     */
    public function merge(array $servers)
    {
        foreach ($servers as $server) {
            $this->add($server);
        }
    }

    /**
     * Returns whether the collection is empty.
     *
     * @return bool Returns `true` if the collection contains no servers and
     *              `false` otherwise.
     */
    public function isEmpty()
    {
        return 0 === count($this->servers);
    }

    /**
     * Returns the collection contents as array.
     *
     * @return Server[] The servers in the collection indexed by their
     *                       names.
     */
    public function toArray()
    {
        return $this->servers;
    }

    /**
     * Returns the default server of the collection.
     *
     * By default, the first added server is the default server. The default
     * server can be changed with {@link setDefaultServer}.
     *
     * @return Server Returns the default server.
     *
     * @throws NoSuchServerException If the collection is empty.
     */
    public function getDefaultServer()
    {
        if (!$this->defaultServer) {
            throw new NoSuchServerException('Cannot get the default server of an empty collection.');
        }

        return $this->defaultServer;
    }

    /**
     * Sets the default server of the collection.
     *
     * @param string $serverName The name of the default server.
     *
     * @throws NoSuchServerException If the server does not exist.
     */
    public function setDefaultServer($serverName)
    {
        $this->defaultServer = $this->get($serverName);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($serverName)
    {
        return $this->contains($serverName);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($serverName)
    {
        return $this->get($serverName);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($key, $server)
    {
        if (null !== $key) {
            throw new LogicException('Keys are not accepted when setting a value by array access.');
        }

        $this->add($server);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($serverName)
    {
        $this->remove($serverName);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->servers);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->servers);
    }
}
