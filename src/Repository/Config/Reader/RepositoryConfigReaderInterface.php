<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Repository\Config\Reader;

use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\Repository\Config\PackageRepositoryConfig;

/**
 * Reads package repository configuration from a file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RepositoryConfigReaderInterface
{
    /**
     * Reads repository configuration from a file.
     *
     * @param string $path The file path to read.
     *
     * @return PackageRepositoryConfig The repository configuration.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function readRepositoryConfig($path);
}
