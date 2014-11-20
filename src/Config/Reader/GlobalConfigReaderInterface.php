<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Config\Reader;

use Puli\PackageManager\Config\GlobalConfig;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;

/**
 * Reads global configuration from a data source.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface GlobalConfigReaderInterface
{
    /**
     * Reads global configuration from a data source.
     *
     * @param mixed $source The data source.
     *
     * @return GlobalConfig The global configuration.
     *
     * @throws FileNotFoundException If the data source was not found.
     * @throws InvalidConfigException If the source contains invalid configuration.
     */
    public function readGlobalConfig($source);
}
