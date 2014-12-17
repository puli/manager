<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package\PackageFile\Reader;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\Package\PackageFile\PackageFile;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;

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
     */
    public function readRootPackageFile($path, Config $baseConfig = null);
}
