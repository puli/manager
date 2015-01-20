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

use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFile;
use Puli\RepositoryManager\Api\Environment\GlobalEnvironment;
use Puli\RepositoryManager\Api\FileNotFoundException;
use Puli\RepositoryManager\Api\NoDirectoryException;
use Puli\RepositoryManager\Config\ConfigFileStorage;
use Puli\RepositoryManager\Config\DefaultConfig;
use Puli\RepositoryManager\Config\EnvConfig;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\PathUtil\Path;

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
class GlobalEnvironmentImpl implements GlobalEnvironment
{
    /**
     * @var string|null
     */
    private $homeDir;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ConfigFile|null
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
     * @param string|null              $homeDir           The path to Puli's home directory.
     * @param ConfigFileStorage        $configFileStorage The global configuration storage.
     * @param EventDispatcherInterface $dispatcher        The event dispatcher.
     *
     * @throws FileNotFoundException If the home directory does not exist.
     * @throws NoDirectoryException If the home directory is not a directory.
     */
    public function __construct($homeDir, ConfigFileStorage $configFileStorage, EventDispatcherInterface $dispatcher)
    {
        if (null !== $homeDir) {
            // If a home directory is set, check it for a global config.json file
            if (!file_exists($homeDir)) {
                throw new FileNotFoundException(sprintf(
                    'Could not load Puli environment: The home directory %s '.
                    'does not exist.',
                    $homeDir
                ));
            }

            if (!is_dir($homeDir)) {
                throw new NoDirectoryException(sprintf(
                    'Could not load Puli environment: The home directory %s is '.
                    'a file. Expected a directory.',
                    $homeDir
                ));
            }

            $this->homeDir = Path::canonicalize($homeDir);
            $this->configFile = $configFileStorage->loadConfigFile(
                $this->homeDir.'/config.json',
                new DefaultConfig()
            );
            $this->config = new EnvConfig($this->configFile->getConfig());
        } else {
            // No home directory: use default config with environment variables
            $this->config = new EnvConfig(new DefaultConfig());
        }

        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getHomeDirectory()
    {
        return $this->homeDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }

    /**
     * {@inheritdoc}
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
