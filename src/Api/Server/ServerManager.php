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

use Puli\Manager\Api\Installer\NoSuchInstallerException;
use Webmozart\Expression\Expression;

/**
 * Manages the asset servers of the application.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ServerManager
{
    /**
     * Adds a server.
     *
     * If a server with the same name exists, the existing server is
     * overwritten.
     *
     * @param Server $server The server to add.
     *
     * @throws NoSuchInstallerException If the installer referred to by the
     *                                  server does not exist.
     */
    public function addServer(Server $server);

    /**
     * Removes a server.
     *
     * If the server does not exist, this method does nothing.
     *
     * @param string $serverName The name of the server.
     */
    public function removeServer($serverName);

    /**
     * Removes all servers matching the given expression.
     *
     * If no matching servers are found, this method does nothing.
     *
     * @param Expression $expr The search criteria.
     */
    public function removeServers(Expression $expr);

    /**
     * Removes all servers.
     *
     * If no servers are found, this method does nothing.
     */
    public function clearServers();

    /**
     * Returns the server with the given name.
     *
     * @param string $serverName The name of the server.
     *
     * @return Server The server.
     *
     * @throws NoSuchServerException If the server does not exist.
     */
    public function getServer($serverName);

    /**
     * Returns all servers.
     *
     * @return ServerCollection The servers.
     */
    public function getServers();

    /**
     * Returns all servers matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return ServerCollection The servers.
     */
    public function findServers(Expression $expr);

    /**
     * Returns whether a server exists.
     *
     * @param string $serverName The name of the server.
     *
     * @return bool Returns `true` if the server exists and `false` otherwise.
     */
    public function hasServer($serverName);

    /**
     * Returns whether the manager has any servers.
     *
     * You can optionally pass an expression to check whether the manager has
     * servers matching that expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has servers and `false`
     *              otherwise.
     */
    public function hasServers(Expression $expr = null);

    /**
     * Sets the default server.
     *
     * By default, the first added server is the default server.
     *
     * @param string $serverName The name of the default server.
     *
     * @throws NoSuchServerException If the server does not exist.
     */
    public function setDefaultServer($serverName);

    /**
     * Returns the default server.
     *
     * By default, the first added server is the default server. The default
     * server can be changed with {@link setDefaultServer()}.
     *
     * @return Server The default server.
     *
     * @throws NoSuchServerException If the collection is empty.
     */
    public function getDefaultServer();
}
