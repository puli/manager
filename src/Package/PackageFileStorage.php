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
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Api\Storage\StorageException;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileSerializer;
use Puli\Manager\Api\Package\PackageFileEncoder;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\UnsupportedVersionException;
use Puli\Manager\Filesystem\FilesystemStorage;
use Puli\Manager\Filesystem\FileWriter;

/**
 * Loads and saves package files.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileStorage
{
    /**
     * @var FilesystemStorage
     */
    private $storage;

    /**
     * @var PackageFileSerializer
     */
    private $serializer;

    /**
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * Creates a new storage.
     *
     * @param Storage               $storage        The file storage.
     * @param PackageFileSerializer $serializer     The package file serializer.
     * @param FactoryManager        $factoryManager The manager used to
     *                                              regenerate the factory class
     *                                              after saving the root
     *                                              package file.
     */
    public function __construct(Storage $storage, PackageFileSerializer $serializer, FactoryManager $factoryManager = null)
    {
        $this->storage = $storage;
        $this->serializer = $serializer;
        $this->factoryManager = $factoryManager;
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
     * @throws StorageException            If the file cannot be read.
     * @throws InvalidConfigException      If the file contains invalid
     *                                     configuration.
     * @throws UnsupportedVersionException If the version of the package file
     *                                     is not supported.
     */
    public function loadPackageFile($path)
    {
        if (!$this->storage->exists($path)) {
            return new PackageFile(null, $path);
        }

        $serialized = $this->storage->read($path);

        return $this->serializer->unserializePackageFile($serialized, $path);
    }

    /**
     * Saves a package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * @param PackageFile $packageFile The package file to save.
     *
     * @throws StorageException If the file cannot be written.
     */
    public function savePackageFile(PackageFile $packageFile)
    {
        $serialized = $this->serializer->serializePackageFile($packageFile);

        $this->storage->write($packageFile->getPath(), $serialized);
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
     * @throws StorageException            If the file cannot be read.
     * @throws InvalidConfigException      If the file contains invalid
     *                                     configuration.
     * @throws UnsupportedVersionException If the version of the package file
     *                                     is not supported.
     */
    public function loadRootPackageFile($path, Config $baseConfig)
    {
        if (!$this->storage->exists($path)) {
            return new RootPackageFile(null, $path, $baseConfig);
        }

        $serialized = $this->storage->read($path);

        return $this->serializer->unserializeRootPackageFile($serialized, $path, $baseConfig);
    }

    /**
     * Saves a root package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * @param RootPackageFile $packageFile The package file to save.
     *
     * @throws StorageException If the file cannot be written.
     */
    public function saveRootPackageFile(RootPackageFile $packageFile)
    {
        $serialized = $this->serializer->serializeRootPackageFile($packageFile);

        $this->storage->write($packageFile->getPath(), $serialized);

        if ($this->factoryManager) {
            $this->factoryManager->autoGenerateFactoryClass();
        }
    }
}
