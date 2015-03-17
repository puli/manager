<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Package;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Event\PackageFileEvent;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\IOException;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileReader;
use Puli\Manager\Api\Package\PackageFileWriter;
use Puli\Manager\Api\Package\RootPackageFile;

/**
 * Loads and saves package files.
 *
 * This class adds a layer on top of {@link PackageFileReader} and
 * {@link PackageFileWriter}. Any logic that is related to the loading and
 * saving of package configuration, but not directly related to the
 * reading/writing of a specific file format, is executed by this class.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileStorage
{
    /**
     * @var PackageFileReader
     */
    private $reader;

    /**
     * @var PackageFileWriter
     */
    private $writer;

    /**
     * Creates a new storage.
     *
     * @param PackageFileReader $reader The package file reader.
     * @param PackageFileWriter $writer The package file writer.
     */
    public function __construct(PackageFileReader $reader, PackageFileWriter $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }

    /**
     * Loads a package file from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * Loaded package files must have a package name set. If none is set, an
     * exception is thrown.
     *
     * @param string $path The path to the package file.
     *
     * @return PackageFile The loaded package file.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadPackageFile($path)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            $packageFile = $this->reader->readPackageFile($path);
        } catch (FileNotFoundException $e) {
            $packageFile = new PackageFile(null, $path);
        }

        return $packageFile;
    }

    /**
     * Saves a package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * @param PackageFile $packageFile The package file to save.
     *
     * @throws IOException If the file cannot be written.
     */
    public function savePackageFile(PackageFile $packageFile)
    {
        $this->writer->writePackageFile($packageFile, $packageFile->getPath());
    }

    /**
     * Loads a root package file from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * @param string $path       The path to the package configuration file.
     * @param Config $baseConfig The configuration that the package will inherit
     *                           its configuration values from.
     *
     * @return RootPackageFile The loaded package file.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadRootPackageFile($path, Config $baseConfig)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            $packageFile = $this->reader->readRootPackageFile($path, $baseConfig);
        } catch (FileNotFoundException $e) {
            $packageFile = new RootPackageFile(null, $path, $baseConfig);
        }

        return $packageFile;
    }

    /**
     * Saves a root package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * @param RootPackageFile $packageFile The package file to save.
     *
     * @throws IOException If the file cannot be written.
     */
    public function saveRootPackageFile(RootPackageFile $packageFile)
    {
        $this->writer->writePackageFile($packageFile, $packageFile->getPath());
    }
}
