<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Repository\Config;

use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\IOException;
use Puli\PackageManager\Repository\Config\Reader\RepositoryConfigReaderInterface;
use Puli\PackageManager\Repository\Config\Writer\RepositoryConfigWriterInterface;

/**
 * Manages the loading and saving of configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageRepositoryConfigStorage
{
    /**
     * @var RepositoryConfigReaderInterface
     */
    private $repositoryConfigReader;

    /**
     * @var RepositoryConfigWriterInterface
     */
    private $repositoryConfigWriter;

    /**
     * Creates a new configuration manager.
     *
     * @param RepositoryConfigReaderInterface $repositoryConfigReader The reader for repository config files.
     * @param RepositoryConfigWriterInterface $repositoryConfigWriter The writer for repository config files.
     */
    public function __construct(
        RepositoryConfigReaderInterface $repositoryConfigReader,
        RepositoryConfigWriterInterface $repositoryConfigWriter
    )
    {
        $this->repositoryConfigReader = $repositoryConfigReader;
        $this->repositoryConfigWriter = $repositoryConfigWriter;
    }

    /**
     * Loads package repository configuration from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * @param string $path The path to the repository configuration file.
     *
     * @return PackageRepositoryConfig The loaded package repository configuration.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadRepositoryConfig($path)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            return $this->repositoryConfigReader->readRepositoryConfig($path);
        } catch (FileNotFoundException $e) {
            return new PackageRepositoryConfig($path);
        }
    }

    /**
     * Saves package repository configuration.
     *
     * The repository configuration is saved to the same path that it was read
     * from.
     *
     * @param PackageRepositoryConfig $config The package repository
     *                                        configuration to save.
     *
     * @throws IOException If the configuration cannot be written.
     */
    public function saveRepositoryConfig(PackageRepositoryConfig $config)
    {
        $this->repositoryConfigWriter->writeRepositoryConfig($config, $config->getPath());
    }
}
