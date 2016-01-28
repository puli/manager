<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Config;

use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
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
 * Loads and saves configuration files.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileStorage
{
    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var JsonConverter
     */
    private $configFileConverter;

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
     * Creates a new configuration file storage.
     *
     * @param Storage             $storage             The file storage.
     * @param JsonConverter       $configFileConverter The JSON converter for
     *                                                 {@link ConfigFile}
     *                                                 instances.
     * @param JsonEncoder         $jsonEncoder         The JSON encoder.
     * @param JsonDecoder         $jsonDecoder         The JSON decoder.
     * @param FactoryManager|null $factoryManager      The manager used to
     *                                                 regenerate the factory
     *                                                 class after saving the
     *                                                 config file.
     */
    public function __construct(
        Storage $storage,
        JsonConverter $configFileConverter,
        JsonEncoder $jsonEncoder,
        JsonDecoder $jsonDecoder,
        FactoryManager $factoryManager = null
    )
    {
        $this->storage = $storage;
        $this->configFileConverter = $configFileConverter;
        $this->jsonEncoder = $jsonEncoder;
        $this->jsonDecoder = $jsonDecoder;
        $this->factoryManager = $factoryManager;
    }

    /**
     * Loads a configuration file from a path.
     *
     * If the path does not exist, an empty configuration file is returned.
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
        $json = $this->storage->read($path);
        $jsonData = $this->decode($json, $path);

        try {
            return $this->configFileConverter->fromJson($jsonData, array(
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
        try {
            $jsonData = $this->configFileConverter->toJson($configFile);
        } catch (ConversionFailedException $e) {
            throw new InvalidConfigException(sprintf(
                'The data written to %s could not be converted: %s',
                $configFile->getPath(),
                $e->getMessage()
            ), 0, $e);
        }

        $json = $this->encode($jsonData, $configFile->getPath());

        $this->storage->write($configFile->getPath(), $json);

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
