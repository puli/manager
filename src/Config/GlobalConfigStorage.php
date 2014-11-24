<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Config;

use Puli\PackageManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\PackageManager\Config\Writer\GlobalConfigWriterInterface;
use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\IOException;

/**
 * Manages the loading and saving of configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalConfigStorage
{
    /**
     * @var GlobalConfigReaderInterface
     */
    private $globalConfigReader;

    /**
     * @var GlobalConfigWriterInterface
     */
    private $globalConfigWriter;

    /**
     * Creates a new configuration manager.
     *
     * @param GlobalConfigReaderInterface     $globalConfigReader     The reader for global config files.
     * @param GlobalConfigWriterInterface     $globalConfigWriter     The writer for global config files.
     */
    public function __construct(
        GlobalConfigReaderInterface $globalConfigReader,
        GlobalConfigWriterInterface $globalConfigWriter
    )
    {
        $this->globalConfigReader = $globalConfigReader;
        $this->globalConfigWriter = $globalConfigWriter;
    }

    /**
     * Loads global configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * @param string $path The path to the global configuration file.
     *
     * @return GlobalConfig The loaded global configuration.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadGlobalConfig($path)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            return $this->globalConfigReader->readGlobalConfig($path);
        } catch (FileNotFoundException $e) {
            return new GlobalConfig($path);
        }
    }

    /**
     * Saves global configuration.
     *
     * The global configuration is saved to the same path that it was read from.
     *
     * @param GlobalConfig $config The global configuration to save.
     *
     * @throws IOException If the configuration cannot be written.
     */
    public function saveGlobalConfig(GlobalConfig $config)
    {
        $this->globalConfigWriter->writeGlobalConfig($config, $config->getPath());
    }
}
