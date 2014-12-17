<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config\ConfigFile;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\Reader\ConfigFileReader;
use Puli\RepositoryManager\Config\ConfigFile\Writer\ConfigFileWriter;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\IOException;

/**
 * Loads and saves configuration files.
 *
 * This class adds a layer on top of {@link ConfigFileReader} and
 * {@link ConfigFileWriter}. Any logic that is related to the loading and saving
 * of configuration files, but not directly related to the reading/writing of a
 * specific file format, is executed by this class.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileStorage
{
    /**
     * @var ConfigFileReader
     */
    private $reader;

    /**
     * @var ConfigFileWriter
     */
    private $writer;

    /**
     * Creates a new configuration file storage.
     *
     * @param ConfigFileReader $reader The configuration file reader.
     * @param ConfigFileWriter $writer The configuration file writer.
     */
    public function __construct(ConfigFileReader $reader, ConfigFileWriter $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }

    /**
     * Loads a configuration file from a path.
     *
     * If the path does not exist, an empty configuration file is returned.
     *
     * @param string $path       The path to the configuration file.
     * @param Config $baseConfig The configuration that the loaded configuration
     *                           will inherit its values from.
     *
     * @return ConfigFile The loaded configuration file.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadConfigFile($path, Config $baseConfig = null)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            return $this->reader->readConfigFile($path, $baseConfig);
        } catch (FileNotFoundException $e) {
            return new ConfigFile($path, $baseConfig);
        }
    }

    /**
     * Saves a configuration file.
     *
     * The configuration file is saved to the same path that it was read from.
     *
     * @param ConfigFile $configFile The configuration file to save.
     *
     * @throws IOException If the file cannot be written.
     */
    public function saveConfigFile(ConfigFile $configFile)
    {
        $this->writer->writeConfigFile($configFile, $configFile->getPath());
    }
}
