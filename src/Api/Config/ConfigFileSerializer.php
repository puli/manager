<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Config;

use Puli\Manager\Api\InvalidConfigException;

/**
 * Serializes and unserializes configuration files.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConfigFileSerializer
{
    /**
     * Serializes a configuration file.
     *
     * @param ConfigFile $configFile The configuration file.
     *
     * @return string The serialized configuration file.
     */
    public function serializeConfigFile(ConfigFile $configFile);

    /**
     * Unserializes a configuration file.
     *
     * @param string      $serialized The serialized file.
     * @param string|null $path       The path of the configuration file.
     * @param Config|null $baseConfig The configuration that the loaded
     *                                configuration will inherit its values
     *                                from.
     *
     * @return ConfigFile The configuration file.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function unserializeConfigFile($serialized, $path = null, Config $baseConfig = null);
}
