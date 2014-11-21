<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\Config\Reader;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\Package\Config\PackageConfig;
use Puli\PackageManager\Package\Config\RootPackageConfig;

/**
 * Reads package configuration from a file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageConfigReaderInterface
{
    /**
     * Reads package configuration from a file.
     *
     * @param string $path The file path to read.
     *
     * @return PackageConfig The package configuration.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the source contains invalid configuration.
     */
    public function readPackageConfig($path);

    /**
     * Reads root package configuration from a file.
     *
     * @param string       $path         The file path to read.
     * @param GlobalConfig $globalConfig The global configuration that the root
     *                                   configuration will inherit its settings
     *                                   from.
     *
     * @return RootPackageConfig The root package configuration.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function readRootPackageConfig($path, GlobalConfig $globalConfig);
}
