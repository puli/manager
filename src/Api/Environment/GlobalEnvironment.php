<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Api\Environment;

use Puli\RepositoryManager\Api\Config\Config;
use Puli\RepositoryManager\Api\Config\ConfigFile;
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
interface GlobalEnvironment
{
    /**
     * Returns the path to the home directory.
     *
     * This method return `null` if no home directory has been set, which
     * happens frequently on web servers. See
     * {@link System::parseHomeDirectory()} for more information.
     *
     * @return string|null The path to the home directory or `null` if none is
     *                     available.
     */
    public function getHomeDirectory();

    /**
     * Returns the configuration.
     *
     * @return Config The configuration.
     */
    public function getConfig();

    /**
     * Returns the configuration file in the home directory.
     *
     * @return ConfigFile|null The configuration file or `null` if no home
     *                         directory was found.
     */
    public function getConfigFile();

    /**
     * Returns the event dispatcher.
     *
     * @return EventDispatcherInterface The event dispatcher.
     */
    public function getEventDispatcher();
}
