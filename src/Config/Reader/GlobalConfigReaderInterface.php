<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config\Reader;

use Puli\RepositoryManager\Config\GlobalConfig;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;

/**
 * Reads global configuration from a file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface GlobalConfigReaderInterface
{
    /**
     * Reads global configuration from a file.
     *
     * @param string $path The file path to read.
     *
     * @return GlobalConfig The global configuration.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function readGlobalConfig($path);
}
