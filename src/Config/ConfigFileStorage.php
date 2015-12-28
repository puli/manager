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
use Puli\Manager\Api\Config\ConfigFileSerializer;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\FileNotFoundException;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Storage\ReadException;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Api\Storage\WriteException;

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
     * @var ConfigFileSerializer
     */
    private $serializer;

    /**
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * Creates a new configuration file storage.
     *
     * @param Storage              $storage        The file storage.
     * @param ConfigFileSerializer $serializer     The configuration file
     *                                             serializer.
     * @param FactoryManager|null  $factoryManager The manager used to regenerate
     *                                             the factory class after saving
     *                                             the config file.
     */
    public function __construct(Storage $storage, ConfigFileSerializer $serializer, FactoryManager $factoryManager = null)
    {
        $this->storage = $storage;
        $this->serializer = $serializer;
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
        $serialized = $this->storage->read($path);

        return $this->serializer->unserializeConfigFile($serialized, $path, $baseConfig);
    }

    /**
     * Saves a configuration file.
     *
     * The configuration file is saved to the same path that it was read from.
     *
     * @param ConfigFile $configFile The configuration file to save.
     *
     * @throws WriteException If the file cannot be written.
     */
    public function saveConfigFile(ConfigFile $configFile)
    {
        $serialized = $this->serializer->serializeConfigFile($configFile);

        $this->storage->write($configFile->getPath(), $serialized);

        if ($this->factoryManager) {
            $this->factoryManager->autoGenerateFactoryClass();
        }
    }
}
