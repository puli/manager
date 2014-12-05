<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config\ConfigFile\Reader;

use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;

/**
 * Reads configuration files.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConfigFileReaderInterface
{
    /**
     * Reads a configuration file.
     *
     * @param string $path The file path to read.
     *
     * @return ConfigFile The configuration file.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function readConfigFile($path);
}
