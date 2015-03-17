<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Package;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;

/**
 * Reads package files.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageFileReader
{
    /**
     * Reads a package file.
     *
     * @param string $path The file path to read.
     *
     * @return PackageFile The package file.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the source contains invalid configuration.
     * @throws UnsupportedVersionException If the version of the package file
     *                                     is not supported by the reader.
     */
    public function readPackageFile($path);

    /**
     * Reads a root package file.
     *
     * @param string $path       The file path to read.
     * @param Config $baseConfig The configuration that the package will inherit
     *                           its configuration values from.
     *
     * @return RootPackageFile The root package file.
     *
     * @throws FileNotFoundException If the file was not found.
     * @throws InvalidConfigException If the file contains invalid configuration.
     * @throws UnsupportedVersionException If the version of the package file
     *                                     is not supported by the reader.
     */
    public function readRootPackageFile($path, Config $baseConfig = null);
}
