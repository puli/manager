<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Asset;

use Puli\Manager\Assert\Assert;
use Rhumsaa\Uuid\Uuid;

/**
 * Maps Puli resources to a public path on a server.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AssetMapping
{
    /**
     * The UUID field in {@link Expression} instances.
     */
    const UUID = 'uuid';

    /**
     * The glob field in {@link Expression} instances.
     */
    const GLOB = 'glob';

    /**
     * The server name field in {@link Expression} instances.
     */
    const SERVER_NAME = 'serverName';

    /**
     * The server path field in {@link Expression} instances.
     */
    const SERVER_PATH = 'serverPath';

    /**
     * @var Uuid
     */
    private $uuid;

    /**
     * @var string
     */
    private $glob;

    /**
     * @var string
     */
    private $serverName;

    /**
     * @var string
     */
    private $serverPath;

    /**
     * Creates the mapping.
     *
     * @param string $glob       A glob for resources in the repository.
     * @param string $serverName The name of the asset server.
     * @param string $serverPath The path of the resource in the document root
     *                           of the server.
     * @param Uuid   $uuid       The UUID of the mapping.
     */
    public function __construct($glob, $serverName, $serverPath, Uuid $uuid = null)
    {
        Assert::stringNotEmpty($glob, 'The glob must be a non-empty string. Got: %s');
        Assert::stringNotEmpty($serverName, 'The server name must be a non-empty string. Got: %s');
        Assert::string($serverPath, 'The public path must be a string. Got: %s');

        $this->uuid = $uuid ?: Uuid::uuid4();
        $this->glob = $glob;
        $this->serverName = $serverName;
        $this->serverPath = '/'.trim($serverPath, '/');
    }

    /**
     * Returns the UUID of the mapping.
     *
     * @return Uuid The UUID of the mapping.
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Returns the glob for the resources in the repository.
     *
     * @return string The repository path.
     */
    public function getGlob()
    {
        return $this->glob;
    }

    /**
     * Returns the name of the mapped server.
     *
     * @return string The server name.
     */
    public function getServerName()
    {
        return $this->serverName;
    }

    /**
     * Returns the path of the resources relative to the server's document root.
     *
     * @return string The public resource path.
     */
    public function getServerPath()
    {
        return $this->serverPath;
    }
}
