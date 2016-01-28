<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Module;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\RootModuleFile;
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
 * Loads and saves module files.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleFileStorage
{
    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var JsonConverter
     */
    private $moduleFileConverter;

    /**
     * @var JsonConverter
     */
    private $rootModuleFileConverter;

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
     * @param Storage             $storage                 The file storage.
     * @param JsonConverter       $moduleFileConverter     The JSON converter for
     *                                                     {@link ModuleFile}
     *                                                     instances.
     * @param JsonConverter       $rootModuleFileConverter The JSON converter
     *                                                     for {@link RootModuleFile}
     *                                                     instances.
     * @param JsonEncoder         $jsonEncoder             The JSON encoder.
     * @param JsonDecoder         $jsonDecoder             The JSON decoder.
     * @param FactoryManager|null $factoryManager          The manager used to
     *                                                     regenerate the factory
     *                                                     class after saving
     *                                                     the root module file.
     */
    public function __construct(
        Storage $storage,
        JsonConverter $moduleFileConverter,
        JsonConverter $rootModuleFileConverter,
        JsonEncoder $jsonEncoder,
        JsonDecoder $jsonDecoder,
        FactoryManager $factoryManager = null
    ) {
        $this->storage = $storage;
        $this->moduleFileConverter = $moduleFileConverter;
        $this->rootModuleFileConverter = $rootModuleFileConverter;
        $this->jsonEncoder = $jsonEncoder;
        $this->jsonDecoder = $jsonDecoder;
        $this->factoryManager = $factoryManager;
    }

    /**
     * Loads a module file from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * Loaded module files must have a module name set. If none is set, an
     * exception is thrown.
     *
     * @param string $path The path to the module file.
     *
     * @return ModuleFile The loaded module file.
     *
     * @throws FileNotFoundException  If the file does not exist.
     * @throws ReadException          If the file cannot be read.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadModuleFile($path)
    {
        $json = $this->storage->read($path);
        $jsonData = $this->decode($json, $path);

        try {
            return $this->moduleFileConverter->fromJson($jsonData, array(
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
     * Saves a module file.
     *
     * The module file is saved to the same path that it was read from.
     *
     * @param ModuleFile $moduleFile The module file to save.
     *
     * @throws WriteException         If the file cannot be written.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function saveModuleFile(ModuleFile $moduleFile)
    {
        try {
            $jsonData = $this->moduleFileConverter->toJson($moduleFile, array(
                'targetVersion' => $moduleFile->getVersion(),
            ));
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The data written to %s could not be converted: %s',
                $moduleFile->getPath(),
                $e->getMessage()
            ), 0, $e);
        }

        $json = $this->encode($jsonData, $moduleFile->getPath());

        $this->storage->write($moduleFile->getPath(), $json);
    }

    /**
     * Loads a root module file from a file path.
     *
     * If the file does not exist, an empty configuration is returned.
     *
     * @param string $path       The path to the module configuration file.
     * @param Config $baseConfig The configuration that the module will inherit
     *                           its configuration values from.
     *
     * @return RootModuleFile The loaded module file.
     *
     * @throws FileNotFoundException  If the file does not exist.
     * @throws ReadException          If the file cannot be read.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadRootModuleFile($path, Config $baseConfig)
    {
        $json = $this->storage->read($path);
        $jsonData = $this->decode($json, $path);

        try {
            return $this->rootModuleFileConverter->fromJson($jsonData, array(
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
     * Saves a root module file.
     *
     * The module file is saved to the same path that it was read from.
     *
     * @param RootModuleFile $moduleFile The module file to save.
     *
     * @throws WriteException         If the file cannot be written.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function saveRootModuleFile(RootModuleFile $moduleFile)
    {
        try {
            $jsonData = $this->rootModuleFileConverter->toJson($moduleFile, array(
                'targetVersion' => $moduleFile->getVersion(),
            ));
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The data written to %s could not be converted: %s',
                $moduleFile->getPath(),
                $e->getMessage()
            ), 0, $e);
        }

        $json = $this->encode($jsonData, $moduleFile->getPath());

        $this->storage->write($moduleFile->getPath(), $json);

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
