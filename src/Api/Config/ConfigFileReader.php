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

use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;

/**
 * Reads configuration files.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConfigFileReader
{
    /**
     * Reads a configuration file.
     *
     * @param string $path       The file path to read.
     * @param Config $baseConfig The configuration that the loaded configuration
     *                           will inherit its values from.
     *
     * @return ConfigFile The configuration file.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function readConfigFile($path, Config $baseConfig = null);
}
