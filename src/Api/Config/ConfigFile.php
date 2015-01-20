<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Config;

use InvalidArgumentException;
use Puli\RepositoryManager\Assert\Assert;

/**
 * A file storing configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFile
{
    /**
     * @var string|null
     */
    private $path;

    /**
     * @var Config
     */
    private $config;

    /**
     * Creates a new configuration file.
     *
     * @param string|null $path  The path where the configuration file is stored
     *                           or `null` if this configuration is not stored
     *                           on the file system.
     * @param Config $baseConfig The configuration that the configuration will
     *                           inherit its values from.
     *
     * @throws InvalidArgumentException If the path is not a string or empty.
     */
    public function __construct($path = null, Config $baseConfig = null)
    {
        Assert::nullOrString($path, 'The path to the configuration file should be a string or null. Got: %s');
        Assert::nullOrNotEmpty($path, 'The path to the configuration file should not be empty.');

        // Inherit from default configuration
        $this->config = new Config($baseConfig);
        $this->path = $path;
    }

    /**
     * Returns the path to the configuration file.
     *
     * @return string|null The path or `null` if this configuration is not
     *                     stored on the file system.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the configuration stored in the file.
     *
     * @return Config The configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }
}
