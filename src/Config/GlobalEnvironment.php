<?php

/*
 * This file is part of the Puli Repository Manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Config;

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
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * Creates the global environment.
     *
     * The passed home directory will be be scanned for a file "config.json".
     * If that file exists, it is loaded into memory. Use
     * {@link getGlobalConfig()} to access the loaded configuration.
     *
     * @param string                   $homeDir             The path to Puli's home directory.
     * @param GlobalConfigStorage      $globalConfigStorage The global configuration storage.
     * @param EventDispatcherInterface $dispatcher          The event dispatcher.
     *
     * @throws FileNotFoundException If the home directory does not exist.
     * @throws NoDirectoryException If the home directory is not a directory.
     */
    public function __construct($homeDir, GlobalConfigStorage $globalConfigStorage, EventDispatcherInterface $dispatcher)
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
        $this->globalConfig = $globalConfigStorage->loadGlobalConfig($homeDir.'/config.json');
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
     * Returns the global configuration.
     *
     * @return GlobalConfig The global configuration.
     */
    public function getGlobalConfig()
    {
        return $this->globalConfig;
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
}
