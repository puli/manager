<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config;

use Puli\RepositoryManager\Api\Config\ConfigFile;
use Puli\RepositoryManager\Api\Environment\GlobalEnvironment;
use Puli\RepositoryManager\Api\Factory\FactoryManager;

/**
 * Manages changes to the global configuration file.
 *
 * Use this class to make persistent changes to the global config.json.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileManagerImpl extends AbstractConfigFileManager
{
    /**
     * @var GlobalEnvironment
     */
    private $environment;

    /**
     * @var ConfigFile
     */
    private $configFile;

    /**
     * @var ConfigFileStorage
     */
    private $configFileStorage;

    /**
     * Creates the configuration manager.
     *
     * @param GlobalEnvironment $environment       The global environment.
     * @param ConfigFileStorage $configFileStorage The configuration file storage.
     * @param FactoryManager    $factoryManager    The manager used to regenerate
     *                                             the Puli factory class after
     *                                             changing the configuration.
     */
    public function __construct(GlobalEnvironment $environment, ConfigFileStorage $configFileStorage, FactoryManager $factoryManager = null)
    {
        parent::__construct($factoryManager);

        $this->environment = $environment;
        $this->configFileStorage = $configFileStorage;
        $this->configFile = $environment->getConfigFile();
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfig()
    {
        return $this->configFile->getConfig();
    }

    /**
     * {@inheritdoc}
     */
    protected function saveConfigFile()
    {
        $this->configFileStorage->saveConfigFile($this->configFile);
    }
}
