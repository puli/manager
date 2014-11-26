<?php

/*
 * This file is part of the Puli PackageManager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\PackageManager\Package\InstallFile;

use Puli\PackageManager\FileNotFoundException;
use Puli\PackageManager\InvalidConfigException;
use Puli\PackageManager\IOException;
use Puli\PackageManager\Package\InstallFile\Reader\InstallFileReaderInterface;
use Puli\PackageManager\Package\InstallFile\Writer\InstallFileWriterInterface;

/**
 * Loads and saves install files.
 *
 * This class adds a layer on top of {@link InstallFileReaderInterface} and
 * {@link InstallFileWriterInterface}. Any logic that is related to the
 * loading and saving of install files, but not directly related to the
 * reading/writing of a specific file format, is executed by this class.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallFileStorage
{
    /**
     * @var InstallFileReaderInterface
     */
    private $reader;

    /**
     * @var InstallFileWriterInterface
     */
    private $writer;

    /**
     * Creates a new storage.
     *
     * @param InstallFileReaderInterface $reader The install file reader.
     * @param InstallFileWriterInterface $writer The install file writer.
     */
    public function __construct(InstallFileReaderInterface $reader, InstallFileWriterInterface $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }

    /**
     * Loads an install file from a file path.
     *
     * If the file does not exist, an empty file is returned.
     *
     * @param string $path The path to the install file.
     *
     * @return InstallFile The loaded install file.
     *
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadInstallFile($path)
    {
        try {
            // Don't use file_exists() to decouple from the file system
            return $this->reader->readInstallFile($path);
        } catch (FileNotFoundException $e) {
            return new InstallFile($path);
        }
    }

    /**
     * Saves an install file.
     *
     * The install file is saved to the same path that it was read from.
     *
     * @param InstallFile $installFile The install file to save.
     *
     * @throws IOException If the file cannot be written.
     */
    public function saveInstallFile(InstallFile $installFile)
    {
        $this->writer->writeInstallFile($installFile, $installFile->getPath());
    }
}
