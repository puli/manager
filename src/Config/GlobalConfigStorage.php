<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config;

use Puli\RepositoryManager\Config\Reader\GlobalConfigReaderInterface;
use Puli\RepositoryManager\Config\Writer\GlobalConfigWriterInterface;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\InvalidConfigException;
use Puli\RepositoryManager\IOException;

/**
 * Loads and saves global configuration.
 *
 * This class adds a layer on top of {@link GlobalConfigReaderInterface} and
 * {@link GlobalConfigWriterInterface}. Any logic that is related to the loading
 * and saving of global configuration, but not directly related to the
 * reading/writing of a specific file format, is executed by this class.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalConfigStorage
{
    /**
     * @var GlobalConfigReaderInterface
     */
    private $reader;

    /**
     * @var GlobalConfigWriterInterface
     */
    private $writer;

    /**
     * Creates a new configuration manager.
     *
     * @param GlobalConfigReaderInterface $reader The reader for global config files.
     * @param GlobalConfigWriterInterface $writer The writer for global config files.
     */
    public function __construct(GlobalConfigReaderInterface $reader, GlobalConfigWriterInterface $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
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
            return $this->reader->readGlobalConfig($path);
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
        $this->writer->writeGlobalConfig($config, $config->getPath());
    }
}
