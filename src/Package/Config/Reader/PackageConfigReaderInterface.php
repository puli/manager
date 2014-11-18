<?php

/*
 * This file is part of the Puli Packages package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Packages\Package\Config\Reader;

use Puli\Packages\FileNotFoundException;
use Puli\Packages\InvalidConfigException;
use Puli\Packages\Package\Config\PackageConfig;
use Puli\Packages\Package\Config\RootPackageConfig;

/**
 * Reads package configuration from a data source.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PackageConfigReaderInterface
{
    /**
     * Reads package configuration from a data source.
     *
     * @param mixed $source The data source.
     *
     * @return PackageConfig The package configuration.
     *
     * @throws FileNotFoundException If the data source was not found.
     * @throws InvalidConfigException If the source contains invalid configuration.
     */
    public function readPackageConfig($source);

    /**
     * Reads root package configuration from a data source.
     *
     * @param mixed $source The data source.
     *
     * @return RootPackageConfig The root package configuration.
     *
     * @throws FileNotFoundException If the data source was not found.
     * @throws InvalidConfigException If the source contains invalid configuration.
     */
    public function readRootPackageConfig($source);
}
