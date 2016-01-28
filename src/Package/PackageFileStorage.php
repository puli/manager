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
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileSerializer;
use Puli\Manager\Api\Package\PackageFileTransformer;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Storage\ReadException;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Api\Storage\WriteException;
use stdClass;
use Webmozart\Json\Conversion\ConversionFailedException;
use Webmozart\Json\Conversion\JsonConverter;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\EncodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;

/**
 * Loads and saves package files.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileStorage
{
    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var JsonConverter
     */
    private $packageFileConverter;

    /**
     * @var JsonConverter
     */
    private $rootPackageFileConverter;

    /**
     * @var JsonEncoder
     */
    private $jsonEncoder;

    /**
     * @var JsonDecoder
     */
    private $jsonDecoder;

    /**
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * Creates a new storage.
     *
     * @param Storage             $storage                  The file storage.
     * @param JsonConverter       $packageFileConverter     The JSON converter for
     *                                                      {@link PackageFile}
     *                                                      instances.
     * @param JsonConverter       $rootPackageFileConverter The JSON converter
     *                                                      for {@link RootPackageFile}
     *                                                      instances.
     * @param JsonEncoder         $jsonEncoder              The JSON encoder.
     * @param JsonDecoder         $jsonDecoder              The JSON decoder.
     * @param FactoryManager|null $factoryManager           The manager used to
     *                                                      regenerate the factory
     *                                                      class after saving
     *                                                      the root package file.
     */
    public function __construct(
        Storage $storage,
        JsonConverter $packageFileConverter,
        JsonConverter $rootPackageFileConverter,
        JsonEncoder $jsonEncoder,
        JsonDecoder $jsonDecoder,
        FactoryManager $factoryManager = null
    )
    {
        $this->storage = $storage;
        $this->packageFileConverter = $packageFileConverter;
        $this->rootPackageFileConverter = $rootPackageFileConverter;
        $this->jsonEncoder = $jsonEncoder;
        $this->jsonDecoder = $jsonDecoder;
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
     * @throws FileNotFoundException  If the file does not exist.
     * @throws ReadException          If the file cannot be read.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadPackageFile($path)
    {
        $json = $this->storage->read($path);
        $jsonData = $this->decode($json, $path);

        try {
            return $this->packageFileConverter->fromJson($jsonData, array(
                'path' => $path,
            ));
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The JSON in %s could not be converted: %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }
    }

    /**
     * Saves a package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * @param PackageFile $packageFile The package file to save.
     *
     * @throws WriteException         If the file cannot be written.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function savePackageFile(PackageFile $packageFile)
    {
        try {
            $jsonData = $this->packageFileConverter->toJson($packageFile, array(
                'targetVersion' => $packageFile->getVersion(),
            ));
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The data written to %s could not be converted: %s',
                $packageFile->getPath(),
                $e->getMessage()
            ), 0, $e);
        }

        $json = $this->encode($jsonData, $packageFile->getPath());

        $this->storage->write($packageFile->getPath(), $json);
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
     * @throws FileNotFoundException  If the file does not exist.
     * @throws ReadException          If the file cannot be read.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadRootPackageFile($path, Config $baseConfig)
    {
        $json = $this->storage->read($path);
        $jsonData = $this->decode($json, $path);

        try {
            return $this->rootPackageFileConverter->fromJson($jsonData, array(
                'path' => $path,
                'baseConfig' => $baseConfig,
            ));
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The JSON in %s could not be converted: %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }
    }

    /**
     * Saves a root package file.
     *
     * The package file is saved to the same path that it was read from.
     *
     * @param RootPackageFile $packageFile The package file to save.
     *
     * @throws WriteException         If the file cannot be written.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function saveRootPackageFile(RootPackageFile $packageFile)
    {
        try {
            $jsonData = $this->rootPackageFileConverter->toJson($packageFile, array(
                'targetVersion' => $packageFile->getVersion(),
            ));
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The data written to %s could not be converted: %s',
                $packageFile->getPath(),
                $e->getMessage()
            ), 0, $e);
        }

        $json = $this->encode($jsonData, $packageFile->getPath());

        $this->storage->write($packageFile->getPath(), $json);

        if ($this->factoryManager) {
            $this->factoryManager->autoGenerateFactoryClass();
        }
    }

    private function encode(stdClass $jsonData, $path)
    {
        try {
            return $this->jsonEncoder->encode($jsonData);
        } catch (EncodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The configuration in %s could not be encoded: %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }
    }

    private function decode($json, $path)
    {
        try {
            return $this->jsonDecoder->decode($json);
        } catch (DecodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The configuration in %s could not be decoded: %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }
    }
}
