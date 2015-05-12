<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Server;

use Exception;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Installer\NoSuchInstallerException;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Manager\Api\Server\ServerManager;
use stdClass;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\Json\JsonValidator;
use Webmozart\Json\ValidationFailedException;

/**
 * A server manager that stores the servers in the package file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileServerManager implements ServerManager
{
    /**
     * The extra key that stores the server data.
     */
    const SERVERS_KEY = 'servers';

    /**
     * @var RootPackageFileManager
     */
    private $rootPackageFileManager;

    /**
     * @var InstallerManager
     */
    private $installerManager;

    /**
     * @var ServerCollection
     */
    private $servers;

    /**
     * @var array
     */
    private $serversData = array();

    public function __construct(RootPackageFileManager $rootPackageFileManager, InstallerManager $installerManager)
    {
        $this->rootPackageFileManager = $rootPackageFileManager;
        $this->installerManager = $installerManager;
    }

    /**
     * {@inheritdoc}
     */
    public function addServer(Server $server)
    {
        $this->assertServersLoaded();

        if (!$this->installerManager->hasInstallerDescriptor($server->getInstallerName())) {
            throw NoSuchInstallerException::forInstallerName($server->getInstallerName());
        }

        $previousServers = $this->servers->toArray();
        $previousData = $this->serversData;

        $this->servers->add($server);
        $this->serversData[$server->getName()] = $this->serverToData($server);

        try {
            $this->persistServersData();
        } catch (Exception $e) {
            $this->servers->replace($previousServers);
            $this->serversData = $previousData;

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeServer($serverName)
    {
        $this->removeServers(Expr::same($serverName, Server::NAME));
    }

    /**
     * {@inheritdoc}
     */
    public function removeServers(Expression $expr)
    {
        $this->assertServersLoaded();

        $previousServers = $this->servers->toArray();
        $previousData = $this->serversData;
        $save = false;

        foreach ($this->servers as $server) {
            if ($server->match($expr)) {
                $this->servers->remove($server->getName());
                unset($this->serversData[$server->getName()]);
                $save = true;
            }
        }

        if (!$save) {
            return;
        }

        try {
            $this->persistServersData();
        } catch (Exception $e) {
            $this->servers->replace($previousServers);
            $this->serversData = $previousData;

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearServers()
    {
        $this->removeServers(Expr::valid());
    }

    /**
     * {@inheritdoc}
     */
    public function getServer($serverName)
    {
        $this->assertServersLoaded();

        return $this->servers->get($serverName);
    }

    /**
     * {@inheritdoc}
     */
    public function getServers()
    {
        $this->assertServersLoaded();

        return $this->servers;
    }

    /**
     * {@inheritdoc}
     */
    public function findServers(Expression $expr)
    {
        $this->assertServersLoaded();

        $servers = array();

        foreach ($this->servers as $server) {
            if ($server->match($expr)) {
                $servers[] = $server;
            }
        }

        return new ServerCollection($servers);
    }

    /**
     * {@inheritdoc}
     */
    public function hasServer($serverName)
    {
        $this->assertServersLoaded();

        return $this->servers->contains($serverName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasServers(Expression $expr = null)
    {
        $this->assertServersLoaded();

        if (!$expr) {
            return !$this->servers->isEmpty();
        }

        foreach ($this->servers as $server) {
            if ($server->match($expr)) {
                return true;
            }
        }

        return false;
    }

    private function assertServersLoaded()
    {
        if (null !== $this->servers) {
            return;
        }

        $serversData = $this->rootPackageFileManager->getExtraKey(self::SERVERS_KEY);

        if ($serversData) {
            $jsonValidator = new JsonValidator();
            $errors = $jsonValidator->validate($serversData, __DIR__.'/../../res/schema/servers-schema-1.0.json');

            if (count($errors) > 0) {
                throw new ValidationFailedException(sprintf(
                    "The extra key \"%s\" is invalid:\n%s",
                    self::SERVERS_KEY,
                    implode("\n", $errors)
                ));
            }
        }

        $this->servers = new ServerCollection();
        $this->serversData = (array) $serversData;

        foreach ($this->serversData as $serverName => $serverData) {
            $this->servers->add($this->dataToServer($serverName, $serverData));
        }
    }

    private function persistServersData()
    {
        if ($this->serversData) {
            $this->rootPackageFileManager->setExtraKey(self::SERVERS_KEY, (object) $this->serversData);
        } else {
            $this->rootPackageFileManager->removeExtraKey(self::SERVERS_KEY);
        }
    }

    private function dataToServer($serverName, stdClass $data)
    {
        return new Server(
            $serverName,
            $data->installer,
            $data->{'document-root'},
            isset($data->{'url-format'}) ? $data->{'url-format'} : Server::DEFAULT_URL_FORMAT,
            isset($data->parameters) ? (array) $data->parameters : array()
        );
    }

    private function serverToData(Server $server)
    {
        $data = new stdClass();
        $data->installer = $server->getInstallerName();
        $data->{'document-root'} = $server->getDocumentRoot();

        if (Server::DEFAULT_URL_FORMAT !== ($urlFormat = $server->getUrlFormat())) {
            $data->{'url-format'} = $urlFormat;
        }

        if ($parameters = $server->getParameterValues()) {
            $data->parameters = (object) $parameters;
        }

        return $data;
    }
}
