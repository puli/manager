<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Environment;

use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFile;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Config\EnvConfig;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\NoDirectoryException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The global environment.
 *
 * This class contains global environment information. It provides access to
 * Puli's home directory, the global configuration and the global event
 * dispatcher.
 *
 * Use {@link getConfig()} to access the global configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalEnvironment
{
    /**
     * @var string
     */
    private $homeDir;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ConfigFile
     */
    private $configFile;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * Creates the global environment.
     *
     * The passed home directory will be be scanned for a file "config.json".
     * If that file exists, it is loaded into memory. Use {@link getConfig()} to
     * access the global configuration.
     *
     * @param string                   $homeDir           The path to Puli's home directory.
     * @param ConfigFileStorage        $configFileStorage The global configuration storage.
     * @param EventDispatcherInterface $dispatcher        The event dispatcher.
     *
     * @throws FileNotFoundException If the home directory does not exist.
     * @throws NoDirectoryException If the home directory is not a directory.
     */
    public function __construct($homeDir, ConfigFileStorage $configFileStorage, EventDispatcherInterface $dispatcher)
    {
        if (!file_exists($homeDir)) {
            throw new FileNotFoundException(sprintf(
                'Could not load Puli environment: The home directory %s does '.
                'not exist.',
                $homeDir
            ));
        }

        if (!is_dir($homeDir)) {
            throw new NoDirectoryException(sprintf(
                'Could not load Puli environment: The home directory %s is a '.
                'file. Expected a directory.',
                $homeDir
            ));
        }

        $this->homeDir = $homeDir;
        $this->configFile = $configFileStorage->loadConfigFile($homeDir.'/config.json');
        $this->config = new EnvConfig($this->configFile->getConfig());
        $this->dispatcher = $dispatcher;
    }

    /**
     * Returns the path to the home directory.
     *
     * @return string The path to the home directory.
     */
    public function getHomeDirectory()
    {
        return $this->homeDir;
    }

    /**
     * Returns the configuration.
     *
     * @return Config The configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns the configuration file in the home directory.
     *
     * @return ConfigFile The configuration file.
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }

    /**
     * Returns the event dispatcher.
     *
     * @return EventDispatcherInterface The event dispatcher.
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Sets the configuration.
     *
     * @param Config $config The configuration.
     */
    protected function setConfig(Config $config)
    {
        $this->config = $config;
    }
}
