<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Json;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
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
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\EncodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;

/**
 * Loads and saves JSON files.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonStorage
{
    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var JsonConverterProvider
     */
    private $converterProvider;

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
     * @param Storage               $storage           The file storage.
     * @param JsonConverterProvider $converterProvider The provider for the JSON
     *                                                 converters.
     * @param JsonEncoder           $jsonEncoder       The JSON encoder.
     * @param JsonDecoder           $jsonDecoder       The JSON decoder.
     * @param FactoryManager|null   $factoryManager    The manager used to
     *                                                 regenerate the factory
     *                                                 class after saving a file.
     */
    public function __construct(
        Storage $storage,
        JsonConverterProvider $converterProvider,
        JsonEncoder $jsonEncoder,
        JsonDecoder $jsonDecoder,
        FactoryManager $factoryManager = null
    ) {
        $this->storage = $storage;
        $this->converterProvider = $converterProvider;
        $this->jsonEncoder = $jsonEncoder;
        $this->jsonDecoder = $jsonDecoder;
        $this->factoryManager = $factoryManager;
    }

    /**
     * Loads a configuration file from a path.
     *
     * @param string      $path       The path to the configuration file.
     * @param Config|null $baseConfig The configuration that the loaded
     *                                configuration will inherit its values
     *                                from.
     *
     * @return ConfigFile The loaded configuration file.
     *
     * @throws FileNotFoundException  If the file does not exist.
     * @throws ReadException          If the file cannot be read.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function loadConfigFile($path, Config $baseConfig = null)
    {
        return $this->loadFile($path, 'Puli\Manager\Api\Config\ConfigFile', array(
            'baseConfig' => $baseConfig,
        ));
    }

    /**
     * Saves a configuration file.
     *
     * The configuration file is saved to the same path that it was read from.
     *
     * @param ConfigFile $configFile The configuration file to save.
     *
     * @throws WriteException         If the file cannot be written.
     * @throws InvalidConfigException If the file contains invalid configuration.
     */
    public function saveConfigFile(ConfigFile $configFile)
    {
        $this->saveFile($configFile, $configFile->getPath());

        if ($this->factoryManager) {
            $this->factoryManager->autoGenerateFactoryClass();
        }
    }

    /**
     * Loads a module file from a file path.
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
        return $this->loadFile($path, 'Puli\Manager\Api\Module\ModuleFile');
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
        $this->saveFile($moduleFile, $moduleFile->getPath(), array(
            'targetVersion' => $moduleFile->getVersion(),
        ));
    }

    /**
     * Loads a root module file from a file path.
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
        return $this->loadFile($path, 'Puli\Manager\Api\Module\RootModuleFile', array(
            'baseConfig' => $baseConfig,
        ));
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
        $this->saveFile($moduleFile, $moduleFile->getPath(), array(
            'targetVersion' => $moduleFile->getVersion(),
        ));

        if ($this->factoryManager) {
            $this->factoryManager->autoGenerateFactoryClass();
        }
    }

    private function loadFile($path, $className, array $options = array())
    {
        $json = $this->storage->read($path);
        $jsonData = $this->decode($json, $path);
        $options['path'] = $path;

        try {
            return $this->converterProvider->getJsonConverter($className)->fromJson($jsonData, $options);
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The JSON in %s could not be converted: %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }
    }

    private function saveFile($file, $path, array $options = array())
    {
        $className = get_class($file);

        try {
            $jsonData = $this->converterProvider->getJsonConverter($className)->toJson($file, $options);
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The data written to %s could not be converted: %s',
                $path,
                $e->getMessage()
            ), 0, $e);
        }

        $json = $this->encode($jsonData, $path);

        $this->storage->write($path, $json);
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
