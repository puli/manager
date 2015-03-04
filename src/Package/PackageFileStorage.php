<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Package;

use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Event\PackageFileEvent;
use Puli\RepositoryManager\Api\FileNotFoundException;
use Puli\RepositoryManager\Api\InvalidConfigException;
use Puli\RepositoryManager\Api\IOException;
use Puli\RepositoryManager\Api\Event\PuliEvents;
use Puli\RepositoryManager\Api\Package\PackageFile;
use Puli\RepositoryManager\Api\Package\PackageFileReader;
use Puli\RepositoryManager\Api\Package\PackageFileWriter;
use Puli\RepositoryManager\Api\Package\RootPackageFile;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Loads and saves package files.
 *
 * This class adds a layer on top of {@link PackageFileReader} and
 * {@link PackageFileWriter}. Any logic that is related to the loading and
 * saving of package configuration, but not directly related to the
 * reading/writing of a specific file format, is executed by this class.
 *
 * The events {@link PuliEvents::LOAD_PACKAGE_FILE} and
 * {@link PuliEvents::SAVE_PACKAGE_FILE} are dispatched when a package file
 * is loaded/saved.
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
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * Creates a new storage.
     *
     * @param PackageFileReader        $reader     The package file reader.
     * @param PackageFileWriter        $writer     The package file writer.
     * @param EventDispatcherInterface $dispatcher The event dispatcher to use.
     */
    public function __construct(PackageFileReader $reader, PackageFileWriter $writer, EventDispatcherInterface $dispatcher)
    {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Loads a package file from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * The event {@link PuliEvents::LOAD_PACKAGE_FILE} is dispatched after
     * loading the file. You can attach listeners to this event to modify the
     * loaded file.
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

        if ($this->dispatcher->hasListeners(PuliEvents::LOAD_PACKAGE_FILE)) {
            $event = new PackageFileEvent($packageFile);
            $this->dispatcher->dispatch(PuliEvents::LOAD_PACKAGE_FILE, $event);
        }

        return $packageFile;
    }

    /**
     * Saves a package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * The event {@link PuliEvents::SAVE_PACKAGE_FILE} is dispatched just
     * before saving the file. You can attach listeners to this event to modify
     * the saved file.
     *
     * @param PackageFile $packageFile The package file to save.
     *
     * @throws IOException If the file cannot be written.
     */
    public function savePackageFile(PackageFile $packageFile)
    {
        if ($this->dispatcher->hasListeners(PuliEvents::SAVE_PACKAGE_FILE)) {
            $event = new PackageFileEvent($packageFile);
            $this->dispatcher->dispatch(PuliEvents::SAVE_PACKAGE_FILE, $event);
        }

        $this->writer->writePackageFile($packageFile, $packageFile->getPath());
    }

    /**
     * Loads a root package file from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * The event {@link PuliEvents::LOAD_PACKAGE_FILE} is dispatched after
     * loading the file. You can attach listeners to this event to modify the
     * loaded file.
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

        if ($this->dispatcher->hasListeners(PuliEvents::LOAD_PACKAGE_FILE)) {
            $event = new PackageFileEvent($packageFile);
            $this->dispatcher->dispatch(PuliEvents::LOAD_PACKAGE_FILE, $event);
        }

        return $packageFile;
    }

    /**
     * Saves a root package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * The event {@link PuliEvents::SAVE_PACKAGE_FILE} is dispatched just
     * before saving the file. You can attach listeners to this event to modify
     * the saved file.
     *
     * @param RootPackageFile $packageFile The package file to save.
     *
     * @throws IOException If the file cannot be written.
     */
    public function saveRootPackageFile(RootPackageFile $packageFile)
    {
        if ($this->dispatcher->hasListeners(PuliEvents::SAVE_PACKAGE_FILE)) {
            $event = new PackageFileEvent($packageFile);
            $this->dispatcher->dispatch(PuliEvents::SAVE_PACKAGE_FILE, $event);
        }

        $this->writer->writePackageFile($packageFile, $packageFile->getPath());
    }
}
